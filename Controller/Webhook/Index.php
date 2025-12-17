<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Webhook;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\IpAddress;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;
use Adyen\Payment\Model\Webhook\WebhookAcceptorType;
use Adyen\Webhook\Exception\AuthenticationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Exception;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class Index implements ActionInterface
{
    /**
     * Json constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param WebhookAcceptorFactory $webhookAcceptorFactory
     * @param ResultFactory $resultFactory
     * @param AdyenNotificationRepositoryInterface $adyenNotificationRepository
     * @param IpAddress $ipAddressHelper
     * @param RemoteAddress $remoteAddress
     * @param Http $http
     */
    public function __construct(
        private readonly Context $context,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly WebhookAcceptorFactory $webhookAcceptorFactory,
        private readonly ResultFactory $resultFactory,
        private readonly AdyenNotificationRepositoryInterface $adyenNotificationRepository,
        private readonly IpAddress $ipAddressHelper,
        private readonly RemoteAddress $remoteAddress,
        private readonly Http $http
    ) {
        $this->enforceAjaxHeaderForMagento23Compatibility();
    }

    public function execute(): ResultInterface
    {
        try {
            if (!$this->ipAddressHelper->isIpAddressValid(
                explode(',', (string) $this->remoteAddress->getRemoteAddress()))) {
                throw new AuthenticationException();
            }

            if (!$this->authenticateRequest()) {
                throw new AuthenticationException();
            }

            $rawContent = (string) $this->context->getRequest()->getContent();
            if (empty($rawContent)) {
                throw new InvalidDataException(
                    __('The webhook payload can not be empty!')
                );
            }

            $rawPayload = json_decode($rawContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidDataException(
                    __('The webhook payload contains invalid JSON!')
                );
            }

            $webhookType = $this->getWebhookType($rawPayload);
            $acceptor = $this->webhookAcceptorFactory->getAcceptor($webhookType);

            $notifications = $acceptor->getNotifications($rawPayload);

            foreach ($notifications as $notification) {
                if (!$notification->isDuplicate()) {
                    $notification = $this->adyenNotificationRepository->save($notification);
                    $this->adyenLogger->addAdyenResult(
                        sprintf("Notification %s is accepted", $notification->getId())
                    );
                }
            }

            return $this->prepareResponse('[accepted]', 202);
        } catch (InvalidDataException $e) {
            $this->adyenLogger->addAdyenResult(
                __('Notification has been accepted but not been stored. See the notification logs.')
            );
            $this->adyenLogger->addAdyenNotification(
                __('The webhook has been accepted but not been stored: %1', $e->getMessage())
            );

            return $this->prepareResponse('[accepted]', 202);
        } catch (LocalizedException $e) {
            return $this->prepareResponse($e->getMessage(), 400);
        } catch (AuthenticationException $e) {
            return $this->prepareResponse(__('Unauthorized'), 401);
        } catch (Exception $e) {
            $this->adyenLogger->addAdyenNotification(
                __('An error occurred while processing the webhook. %1', $e->getMessage())
            );

            return $this->prepareResponse(
                __('An error occurred while handling this webhook!'),
                500
            );
        }
    }

    /**
     * @throws InvalidDataException
     */
    private function getWebhookType(array $payload): WebhookAcceptorType
    {
        if (
            isset($payload['notificationItems'][0]['NotificationRequestItem']) &&
            isset($payload['notificationItems'][0]['NotificationRequestItem']['eventCode'])
        ) {
            return WebhookAcceptorType::STANDARD;
        }

        if (isset($payload['type']) && str_contains($payload['type'], 'token')) {
            return WebhookAcceptorType::TOKEN;
        }

        throw new InvalidDataException(__('Unable to determine webhook type from payload.'));
    }

    /**
     * @param string $message
     * @param int $responseCode
     * @return ResultInterface
     */
    private function prepareResponse(string $message, int $responseCode): ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/html');
        $result->setStatusHeader($responseCode);
        $result->setContents($message);

        return $result;
    }

    private function enforceAjaxHeaderForMagento23Compatibility(): void
    {
        if (interface_exists(CsrfAwareActionInterface::class)) {
            $request = $this->context->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
    }

    private function authenticateRequest(): bool
    {
        $expectedUsername = $this->configHelper->getNotificationsUsername();
        $expectedPassword = $this->configHelper->getNotificationsPassword();

        $requestUsername = $this->http->getServer('PHP_AUTH_USER');
        $requestPassword = $this->http->getServer('PHP_AUTH_PW');

        if (!isset($expectedUsername, $expectedPassword, $requestUsername, $requestPassword)) {
            return false;
        }

        $usernameIsValid = hash_equals($expectedUsername, $requestUsername);
        $passwordIsValid = hash_equals($expectedPassword, $requestPassword);

        if (!$usernameIsValid || !$passwordIsValid) {
            return false;
        }
        return true;
    }
}

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

use Adyen\AdyenException;
use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Webhook;
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
use Magento\Framework\Exception\AlreadyExistsException;

class Index implements ActionInterface
{
    /**
     * Json constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param WebhookAcceptorFactory $webhookAcceptorFactory
     * @param Webhook $webhookHelper
     * @param ResultFactory $resultFactory
     * @param AdyenNotificationRepositoryInterface $adyenNotificationRepository
     */
    public function __construct(
        private readonly Context $context,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly WebhookAcceptorFactory $webhookAcceptorFactory,
        private readonly Webhook $webhookHelper,
        private readonly ResultFactory $resultFactory,
        private readonly AdyenNotificationRepositoryInterface $adyenNotificationRepository
    ) {
        $this->enforceAjaxHeaderForMagento23Compatibility();
    }

    public function execute(): ResultInterface
    {
        try {
            if (!$this->authenticateRequest()) {
                throw new AuthenticationException();
            }

            $rawContent = (string) $this->context->getRequest()->getContent();
            if (empty($rawContent)) {
                throw new InvalidDataException();
            }

            $rawPayload = json_decode($rawContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidDataException();
            }

            if (!$this->webhookHelper->isIpValid($rawPayload)) {
                throw new AuthenticationException();
            }

            $webhookType = $this->getWebhookType($rawPayload);
            $acceptor = $this->webhookAcceptorFactory->getAcceptor($webhookType);

            $notifications = $acceptor->getNotifications($rawPayload);

            foreach ($notifications as $notification) {
                if ($notification->isDuplicate()) {
                    $this->adyenLogger->addAdyenResult(sprintf(
                        "Duplicate notification with pspReference %s has been skipped.",
                        $notification->getPspReference()
                    ));
                } else {
                    $notification = $this->adyenNotificationRepository->save($notification);
                    $this->adyenLogger->addAdyenResult(
                        sprintf("Notification %s is accepted", $notification->getId())
                    );
                }
            }

            return $this->prepareResponse('[accepted]', 200);
        } catch (AuthenticationException $e) {
            return $this->prepareResponse(__('Unauthorized'), 401);
        } catch (InvalidDataException $e) {
            return $this->prepareResponse(__('The request does not contain a valid webhook!'), 400);
        } catch (Exception $e) {
            $this->adyenLogger->addAdyenNotification($e->getMessage(), $rawPayload ?? []);

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

        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            return false;
        }

        $usernameIsValid = hash_equals($expectedUsername, $_SERVER['PHP_AUTH_USER']);
        $passwordIsValid = hash_equals($expectedPassword, $_SERVER['PHP_AUTH_PW']);

        if (!$usernameIsValid || !$passwordIsValid) {
            return false;
        }
        return true;
    }
}

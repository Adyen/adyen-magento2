<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Webhook;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;
use Adyen\Payment\Model\Webhook\WebhookAcceptorType;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;

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
     */
    public function __construct(
        private readonly Context $context,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly WebhookAcceptorFactory $webhookAcceptorFactory,
        private readonly Webhook $webhookHelper
    ) {
        $this->enforceAjaxHeaderForMagento23Compatibility();
    }

    /**
     * @throws LocalizedException
     */
    public function execute(): void
    {
        if (!$this->authenticateRequest()) {
            $this->return401();
            return;
        }

        $rawContent = (string) $this->context->getRequest()->getContent();
        if (empty($rawContent)) {
            throw new LocalizedException(__('Empty request body.'));
        }

        $rawPayload = json_decode($rawContent, true);

        if (!is_array($rawPayload)) {
            throw new LocalizedException(__('Invalid JSON payload.'));
        }

        if (!$this->webhookHelper->isIpValid($rawPayload)) {
            $this->return401();
            return;
        }

        $acceptedMessage = '[accepted]';

        try {
            $webhookType = $this->getWebhookType($rawPayload);
            $acceptor = $this->webhookAcceptorFactory->getAcceptor($webhookType);

            if (!$acceptor->validate($rawPayload)) {
                $this->return401();
                return;
            }

            $notifications = $acceptor->toNotificationList($rawPayload);

            foreach ($notifications as $notification) {
                $notification->save();
                $this->adyenLogger->addAdyenResult(sprintf("Notification %s is accepted", $notification->getId()));
            }

            $this->context->getResponse()
                ->clearHeader('Content-Type')
                ->setHeader('Content-Type', 'text/html')
                ->setBody($acceptedMessage);
            return;
        } catch (AdyenException $e) {
            $this->adyenLogger->addAdyenNotification($e->getMessage());

            $this->context->getResponse()
                ->setHttpResponseCode(500)
                ->setBody(__('An error occurred while handling this notification!'));

            return;
        } catch (\Throwable $e) {
            throw new LocalizedException(__('Webhook processing failed: %1', $e->getMessage()));
        }
    }

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

        throw new \UnexpectedValueException('Unable to determine webhook type from payload.');
    }

    private function return401(string $message = 'Unauthorized'): void
    {
        $response = $this->context->getResponse();
        $response->setHttpResponseCode(401);
        $response->setBody($message);
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

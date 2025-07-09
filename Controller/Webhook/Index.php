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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;
use Adyen\Payment\Model\Webhook\WebhookAcceptorType;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;

class Index implements ActionInterface
{
    private Context $context;
    /**
     * Json constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param NotificationReceiver $notificationReceiver
     * @param WebhookAcceptorFactory $webhookAcceptorFactory
     * @param Data $adyenHelper
     */
    public function __construct(
        Context $context,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly NotificationReceiver $notificationReceiver,
        private readonly WebhookAcceptorFactory $webhookAcceptorFactory,
        private readonly Data $adyenHelper
    ) {
        $this->context = $context;
        $this->enforceAjaxHeaderForMagento23Compatibility();
    }

    /**
     * @throws LocalizedException
     */
    public function execute(): void
    {
        $rawPayload = json_decode((string) $this->context->getRequest()->getContent(), true);
        $acceptedMessage = '[accepted]';

        try {
            $webhookType = $this->getWebhookType($rawPayload);
            $acceptor = $this->webhookAcceptorFactory->getAcceptor($webhookType);

            if ($webhookType == WebhookAcceptorType::STANDARD) {
                if (!$this->isNotificationModeValid($rawPayload)) {
                    $this->return401();
                    return;
                }

                foreach ($rawPayload['notificationItems'] as $notificationItemWrapper) {
                    $item = $notificationItemWrapper['NotificationRequestItem'] ?? $notificationItemWrapper;

                    if (!$acceptor->authenticate($item) || !$acceptor->validate($item)) {
                        $this->return401();
                        return;
                    }

                    $notification = $acceptor->toNotification($item, $rawPayload['live']);
                    $notification->save();

                    $this->adyenLogger->addAdyenResult(sprintf("Notification %s is accepted", $notification->getId()));
                }

                // Optional: check for unprocessed notifications for test mode
                $cronCheckTest = $rawPayload['notificationItems'][0]['NotificationRequestItem']['pspReference']
                    ?? $rawPayload['notificationItems'][0]['tokenId']
                    ?? null;

                if ($cronCheckTest && $this->notificationReceiver->isTestNotification($cronCheckTest)) {
                    $unprocessedNotifications = $this->adyenHelper->getUnprocessedNotifications();
                    if ($unprocessedNotifications > 0) {
                        $acceptedMessage .= "\nYou have $unprocessedNotifications unprocessed notifications.";
                    }
                }
            } else {
                // Token lifecycle webhook
                if (!$acceptor->authenticate($rawPayload) || !$acceptor->validate($rawPayload)) {
                    $this->return401();
                    return;
                }

                $notification = $acceptor->toNotification($rawPayload, $rawPayload['environment'] ?? 'test');
                $notification->save();

                $this->adyenLogger->addAdyenResult(sprintf("Notification %s is accepted", $notification->getId()));
            }

            $this->context->getResponse()
                ->clearHeader('Content-Type')
                ->setHeader('Content-Type', 'text/html')
                ->setBody($acceptedMessage);
            return;

        } catch (\Throwable $e) {
            throw new LocalizedException(__('Webhook processing failed: %1', $e->getMessage()));
        }
    }

    private function getWebhookType(array $payload): WebhookAcceptorType
    {
        if (isset($payload['eventCode'])) {
            return WebhookAcceptorType::STANDARD;
        }

        if (isset($payload['type']) && str_contains($payload['type'], 'token')) {
            return WebhookAcceptorType::TOKEN;
        }

        throw new \UnexpectedValueException('Unable to determine webhook type from payload.');
    }

    private function isNotificationModeValid(array $payload): bool
    {
        if (!isset($payload['live'])) {
            return false;
        }

        return $this->notificationReceiver->validateNotificationMode(
            $payload['live'],
            $this->configHelper->isDemoMode()
        );
    }

    private function return401(): void
    {
        $this->context->getResponse()->setHttpResponseCode(401);
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
}

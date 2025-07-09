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
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;
use Adyen\Payment\Api\Webhook\WebhookAcceptorInterface;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Config\Definition\Exception\Exception;

class Index extends Action
{

    /**
     * Json constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param NotificationReceiver $notificationReceiver
     * @param WebhookAcceptorFactory $webhookAcceptorFactory
     */
    public function __construct(
        Context $context,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly NotificationReceiver $notificationReceiver,
        private readonly WebhookAcceptorFactory $webhookAcceptorFactory
    ) {
        parent::__construct($context);
        $this->enforceAjaxHeaderForMagento23Compatibility();
    }

    /**
     * @throws LocalizedException
     */
    public function execute(): void
    {
        $notificationItems = json_decode((string) $this->getRequest()->getContent(), true);

        if (!$this->isNotificationModeValid($notificationItems)) {
            $this->return401();
            return;
        }

        try {
            $acceptedMessage = '';

            foreach ($notificationItems['notificationItems'] as $notificationItemWrapper) {
                $item = $notificationItemWrapper['NotificationRequestItem'] ?? $notificationItemWrapper;

                $webhookType = $this->getWebhookType($item);
                $acceptor = $this->webhookAcceptorFactory->getAcceptor($webhookType);

                if (!$acceptor->authenticate($item) || !$acceptor->validate($item)) {
                    $this->return401();
                    return;
                }

                $notification = $acceptor->toNotification($item, $notificationItems['live']);
                $notification->save();

                $this->adyenLogger->addAdyenResult(sprintf("Notification %s is accepted", $notification->getId()));
                $acceptedMessage = "[accepted]";
            }

            // Optional: check for unprocessed notifications for test mode
            $cronCheckTest = $notificationItems['notificationItems'][0]['NotificationRequestItem']['pspReference']
                ?? $notificationItems['notificationItems'][0]['tokenId']
                ?? null;

            if ($cronCheckTest && $this->notificationReceiver->isTestNotification($cronCheckTest)) {
                $unprocessedNotifications = $this->adyenHelper->getUnprocessedNotifications();
                if ($unprocessedNotifications > 0) {
                    $acceptedMessage .= "\nYou have " . $unprocessedNotifications . " unprocessed notifications.";
                }
            }

            $this->getResponse()
                ->clearHeader('Content-Type')
                ->setHeader('Content-Type', 'text/html')
                ->setBody($acceptedMessage);
            return;

        } catch (Exception $e) {
            throw new LocalizedException(__('Webhook processing failed: %1', $e->getMessage()));
        }
    }

    private function getWebhookType(array $payload): string
    {
        if (isset($payload['eventCode'])) {
            return WebhookAcceptorInterface::TYPE_STANDARD;
        }

        if (isset($payload['type']) && str_contains($payload['type'], 'token')) {
            return WebhookAcceptorInterface::TYPE_TOKEN;
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
        $this->getResponse()->setHttpResponseCode(401);
    }

    private function enforceAjaxHeaderForMagento23Compatibility(): void
    {
        if (interface_exists(CsrfAwareActionInterface::class)) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
    }
}

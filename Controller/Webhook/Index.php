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

use Adyen\Exception\AuthenticationException;
use Adyen\Exception\MerchantAccountCodeException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\IpAddress;
use Adyen\Payment\Helper\RateLimiter;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\NotificationFactory;
use Adyen\Webhook\Exception\HMACKeyValidationException;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Receiver\HmacSignature;
use Adyen\Webhook\Receiver\NotificationReceiver;
use DateTime;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;

/**
 * Class Json extends Action
 */
class Index extends Action
{
    /**
     * @var NotificationFactory
     */
    private $notificationFactory;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var IpAddress
     */
    private $ipAddressHelper;

    /**
     * @var RateLimiter
     */
    private $rateLimiterHelper;

    /**
     * @var HmacSignature
     */
    private $hmacSignature;

    /**
     * @var NotificationReceiver
     */
    private $notificationReceiver;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    private Http $request;

    private WebhookAcceptorFactory $webhookAcceptorFactory;

    /**
     * Json constructor.
     *
     * @param Context $context
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     * @param SerializerInterface $serializer
     * @param Config $configHelper
     * @param IpAddress $ipAddressHelper
     * @param RateLimiter $rateLimiterHelper
     * @param HmacSignature $hmacSignature
     * @param NotificationReceiver $notificationReceiver
     * @param RemoteAddress $remoteAddress
     * @param WebhookAcceptorFactory $webhookAcceptorFactory
     */
    public function __construct(
        Context $context,
        NotificationFactory $notificationFactory,
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        SerializerInterface $serializer,
        Config $configHelper,
        IpAddress $ipAddressHelper,
        RateLimiter $rateLimiterHelper,
        HmacSignature $hmacSignature,
        NotificationReceiver $notificationReceiver,
        RemoteAddress $remoteAddress,
        Http $request,
        WebhookAcceptorFactory $webhookAcceptorFactory
    ) {
        parent::__construct($context);
        $this->notificationFactory = $notificationFactory;
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->serializer = $serializer;
        $this->configHelper = $configHelper;
        $this->ipAddressHelper = $ipAddressHelper;
        $this->rateLimiterHelper = $rateLimiterHelper;
        $this->hmacSignature = $hmacSignature;
        $this->notificationReceiver = $notificationReceiver;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
        $this->webhookAcceptorFactory = $webhookAcceptorFactory;

        // Fix for Magento2.3 adding isAjax to the request params
        if (interface_exists(CsrfAwareActionInterface::class)) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
    }

    /**
     * @throws LocalizedException
     */
    public function execute(): void
    {
        // Read JSON encoded notification body
        $notificationItems = json_decode((string) $this->getRequest()->getContent(), true);

        // Check notification mode
        if (!isset($notificationItems['live'])) {
            $this->return401();
            return;
        }
        $notificationMode = $notificationItems['live'];
        $demoMode = $this->configHelper->isDemoMode();
        if (!$this->notificationReceiver->validateNotificationMode($notificationMode, $demoMode)) {
            throw new LocalizedException(
                __('Mismatch between Live/Test modes of Magento store and the Adyen platform')
            );
        }

        try {
            $acceptedMessage = '';

            foreach ($notificationItems['notificationItems'] as $notificationItemWrapper) {
                // Handle both standard and token payload structures
                $item = $notificationItemWrapper['NotificationRequestItem'] ?? $notificationItemWrapper;

                $acceptor = $this->webhookAcceptorFactory->getAcceptor($item);

                if (!$acceptor->authenticate($item) || !$acceptor->validate($item)) {
                    $this->return401();
                    return;
                }

                $notification = $acceptor->toNotification($item, $notificationMode);
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
            throw new LocalizedException(__($e->getMessage()));
        }

    }
}

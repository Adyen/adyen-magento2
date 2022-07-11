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

namespace Adyen\Payment\Controller\Process;

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
use Magento\Framework\App\Request\Http as Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Class Json extends Action
 */
class Json extends Action
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
        NotificationReceiver $notificationReceiver
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
    public function execute()
    {
        // if version is in the notification string show the module version
        $response = $this->getRequest()->getParams();
        if (isset($response['version'])) {
            $this->getResponse()
                ->clearHeader('Content-Type')
                ->setHeader('Content-Type', 'text/html')
                ->setBody($this->adyenHelper->getModuleVersion());

            return;
        }

        // Read JSON encoded notification body
        $notificationItems = json_decode($this->getRequest()->getContent(), true);

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
            // Process each notification item
            $acceptedMessage = '';
            foreach ($notificationItems['notificationItems'] as $notificationItem) {
                $status = $this->processNotification(
                    $notificationItem['NotificationRequestItem'],
                    $notificationMode
                );

                if ($status !== true) {
                    $this->return401();
                    return;
                }

                $acceptedMessage = "[accepted]";
            }

            // Run the query for checking unprocessed notifications, do this only for test notifications coming
            // from the Adyen Customer Area
            $cronCheckTest = $notificationItems['notificationItems'][0]['NotificationRequestItem']['pspReference'];
            if ($this->notificationReceiver->isTestNotification($cronCheckTest)) {
                $unprocessedNotifications = $this->adyenHelper->getUnprocessedNotifications();
                if ($unprocessedNotifications > 0) {
                    $acceptedMessage .= "\nYou have " . $unprocessedNotifications . " unprocessed notifications.";
                }
            }

            $this->adyenLogger->addAdyenNotification("The result is accepted");

            $this->getResponse()
                ->clearHeader('Content-Type')
                ->setHeader('Content-Type', 'text/html')
                ->setBody($acceptedMessage);
            return;
        } catch (Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * HTTP Authentication of the notification
     *
     * @param array $response
     * @return bool
     * @throws AuthenticationException
     * @throws MerchantAccountCodeException
     */
    private function isAuthorised(array $response)
    {
        // Add CGI support
        $this->fixCgiHttpAuthentication();

        $authResult = $this->notificationReceiver->isAuthenticated(
            $response,
            $this->configHelper->getMerchantAccount(),
            $this->configHelper->getNotificationsUsername(),
            $this->configHelper->getNotificationsPassword()
        );

        // if the number of wrongful attempts is not less than 6, save it in cache
        if($this->rateLimiterHelper->getNumberOfAttempts() >= $this->rateLimiterHelper::NUMBER_OF_ATTEMPTS) {
            $this->rateLimiterHelper->saveSessionIdIpAddressToCache();
            return false;
        }

        // if there is no auth result, save it in cache
        if(!$authResult) {
            $this->rateLimiterHelper->saveSessionIdIpAddressToCache();
            return false;
        }

        return $authResult;
    }

    /**
     * save notification into the database for cronjob to execute notification
     *
     * @param $response
     * @param $notificationMode
     * @return bool
     * @throws AuthenticationException
     * @throws MerchantAccountCodeException
     * @throws HMACKeyValidationException
     * @throws InvalidDataException
     */
    private function processNotification(array $response, $notificationMode)
    {
        if (!$this->isAuthorised($response)) {
            return false;
        }

        // Validate if Ip check is enabled and if the notification comes from a verified IP
        if ($this->configHelper->getNotificationsIpCheck() && !$this->isIpValid()) {
            $this->adyenLogger->addAdyenNotification(
                "Notification has been rejected because the IP address could not be verified"
            );
            return false;
        }

        // Validate the Hmac calculation
        $hasHmacCheck = $this->configHelper->getNotificationsHmacCheck() &&
            $this->hmacSignature->isHmacSupportedEventCode($response);
        if ($hasHmacCheck && !$this->notificationReceiver->validateHmac(
            $response,
            $this->configHelper->getNotificationsHmacKey()
        )) {
            $this->adyenLogger->addAdyenNotification(
                'HMAC key validation failed ' . json_encode($response)
            );
            return false;
        }

        $this->adyenLogger->addAdyenNotification(
            "The content of the notification item is: " . json_encode($response)
        );

        // Handling duplicates
        if ($this->isDuplicate($response)) {
            return true;
        }

        $notification = $this->notificationFactory->create();
        $this->loadNotificationFromRequest($notification, $response);
        $notification->setLive($notificationMode);
        $notification->save();

        return true;
    }

    /**
     * @param Notification $notification
     * @param array $requestItem notification received from Adyen
     */
    private function loadNotificationFromRequest(Notification $notification, array $requestItem)
    {
        if (isset($requestItem['pspReference'])) {
            $notification->setPspreference($requestItem['pspReference']);
        }
        if (isset($requestItem['originalReference'])) {
            $notification->setOriginalReference($requestItem['originalReference']);
        }
        if (isset($requestItem['merchantReference'])) {
            $notification->setMerchantReference($requestItem['merchantReference']);
        }
        if (isset($requestItem['eventCode'])) {
            $notification->setEventCode($requestItem['eventCode']);
        }
        if (isset($requestItem['success'])) {
            $notification->setSuccess($requestItem['success']);
        }
        if (isset($requestItem['paymentMethod'])) {
            $notification->setPaymentMethod($requestItem['paymentMethod']);
        }
        if (isset($requestItem['reason'])) {
            $notification->setReason($requestItem['reason']);
        }
        if (isset($requestItem['done'])) {
            $notification->setDone($requestItem['done']);
        }
        if (isset($requestItem['amount'])) {
            $notification->setAmountValue($requestItem['amount']['value']);
            $notification->setAmountCurrency($requestItem['amount']['currency']);
        }
        if (isset($requestItem['additionalData'])) {
            $notification->setAdditionalData($this->serializer->serialize($requestItem['additionalData']));
        }

        // do this to set both fields in the correct timezone
        $date = new DateTime();
        $notification->setCreatedAt($date);
        $notification->setUpdatedAt($date);
    }

    /**
     * Check if remote IP address is from Adyen
     *
     * @return bool
     */
    private function isIpValid()
    {
        $ipAddress = [];
        //Getting remote and possibly forwarded IP addresses
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = explode(',', $_SERVER['REMOTE_ADDR']);
        }
        return $this->ipAddressHelper->isIpAddressValid($ipAddress);
    }

    /**
     * If notification is already saved ignore it
     *
     * @param $response
     * @return mixed
     */
    private function isDuplicate(array $response)
    {
        $originalReference = null;
        if (isset($response['originalReference'])) {
            $originalReference = trim($response['originalReference']);
        }
        $notification = $this->notificationFactory->create();
        $notification->setPspreference(trim($response['pspReference']));
        $notification->setEventCode(trim($response['eventCode']));
        $notification->setSuccess(trim($response['success']));
        $notification->setOriginalReference($originalReference);

        return $notification->isDuplicate();
    }

    /**
     * Fix these global variables for the CGI
     */
    private function fixCgiHttpAuthentication()
    {
        // do nothing if values are already there
        if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
            return;
        } elseif (isset($_SERVER['REDIRECT_REMOTE_AUTHORIZATION']) &&
            $_SERVER['REDIRECT_REMOTE_AUTHORIZATION'] != ''
        ) {
            list(
                $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']
                ) =
                explode(':', base64_decode($_SERVER['REDIRECT_REMOTE_AUTHORIZATION']), 2);
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            list(
                $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']
                ) =
                explode(':', base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6)), 2);
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            list(
                $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']
                ) =
                explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)), 2);
        } elseif (!empty($_SERVER['REMOTE_USER'])) {
            list(
                $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']
                ) =
                explode(':', base64_decode(substr($_SERVER['REMOTE_USER'], 6)), 2);
        } elseif (!empty($_SERVER['REDIRECT_REMOTE_USER'])) {
            list(
                $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']
                ) =
                explode(':', base64_decode(substr($_SERVER['REDIRECT_REMOTE_USER'], 6)), 2);
        }
    }

    /**
     * Return a 401 result
     */
    private function return401()
    {
        $this->getResponse()->setHttpResponseCode(401);
    }
}

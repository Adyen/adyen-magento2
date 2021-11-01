<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Process;

use Adyen\Webhook\Receiver\HmacSignature;
use Adyen\Webhook\Receiver\NotificationReceiver;
use Symfony\Component\Config\Definition\Exception\Exception;
use Magento\Framework\App\Request\Http as Http;

/**
 * Class Json extends Action
 */
class Json extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Adyen\Payment\Model\NotificationFactory
     */
    private $notificationFactory;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Adyen\Payment\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Adyen\Payment\Helper\IpAddress
     */
    protected $ipAddressHelper;

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
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Adyen\Payment\Helper\Config $configHelper
     * @param \Adyen\Payment\Helper\IpAddress $ipAddressHelper
     * @param HmacSignature $hmacSignature
     * @param NotificationReceiver $notificationReceiver
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Adyen\Payment\Model\NotificationFactory $notificationFactory,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Adyen\Payment\Helper\Config $configHelper,
        \Adyen\Payment\Helper\IpAddress $ipAddressHelper,
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
        $this->hmacSignature = $hmacSignature;
        $this->notificationReceiver = $notificationReceiver;

        // Fix for Magento2.3 adding isAjax to the request params
        if (interface_exists(\Magento\Framework\App\CsrfAwareActionInterface::class)) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
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
        $notificationItems = json_decode(file_get_contents('php://input'), true);

        // Check notification mode
        if (!isset($notificationItems['live'])) {
            $this->return401();
            return;
        }
        $notificationMode = $notificationItems['live'];
        $demoMode = $this->adyenHelper->getAdyenAbstractConfigData('demo_mode');
        if (!$this->notificationReceiver->validateNotificationMode($notificationMode, $demoMode)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Mismatch between Live/Test modes of Magento store and the Adyen platform')
            );
        }

        try {
            // Authorize notification
            if (!isset($notificationItems['notificationItems'][0]['NotificationRequestItem'])) {
                return false;
            }
            $firstNotificationItem = $notificationItems['notificationItems'][0]['NotificationRequestItem'];
            // Add CGI support
            $this->fixCgiHttpAuthentication();
            if (!$this->notificationReceiver->isAuthenticated(
                $firstNotificationItem,
                $this->configHelper->getMerchantAccount(),
                $this->configHelper->getNotificationsUsername(),
                $this->configHelper->getNotificationsPassword()
            )) {
                $this->return401();
                return false;
            }

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
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * save notification into the database for cronjob to execute notification
     *
     * @param $response
     * @param $notificationMode
     * @return bool
     * @throws \Adyen\Webhook\Exception\HMACKeyValidationException
     * @throws \Adyen\Webhook\Exception\InvalidDataException
     */
    protected function processNotification($response, $notificationMode)
    {
        // Validate if Ip check is enabled and if the notification comes from a verified IP
        if ($this->configHelper->getNotificationsIpCheck() && !$this->isIpValid()) {
            $this->adyenLogger->addAdyenNotification(
                "Notification has been rejected because the IP address could not be verified"
            );
            return false;
        }
        if ($this->configHelper->getNotificationsHmacCheck() &&
            $this->hmacSignature->isHmacSupportedEventCode($response)
        ) {
            // Validate the Hmac calculation
            if (!$this->notificationReceiver->validateHmac(
                $response,
                $this->configHelper->getNotificationsHmacKey()
            )) {
                $this->adyenLogger->addAdyenNotification(
                    'HMAC key validation failed ' . json_encode($response)
                );
                return false;
            }
        }

        // log the notification
        $this->adyenLogger->addAdyenNotification(
            "The content of the notification item is: " . json_encode($response)
        );

        // check if notification already exists
        if ($this->isDuplicate($response)) {
            // duplicated so do nothing but return accepted to Adyen
            return true;
        }

        $notification = $this->notificationFactory->create();

        if (isset($response['pspReference'])) {
            $notification->setPspreference($response['pspReference']);
        }
        if (isset($response['originalReference'])) {
            $notification->setOriginalReference($response['originalReference']);
        }
        if (isset($response['merchantReference'])) {
            $notification->setMerchantReference($response['merchantReference']);
        }
        if (isset($response['eventCode'])) {
            $notification->setEventCode($response['eventCode']);
        }
        if (isset($response['success'])) {
            $notification->setSuccess($response['success']);
        }
        if (isset($response['paymentMethod'])) {
            $notification->setPaymentMethod($response['paymentMethod']);
        }
        if (isset($response['amount'])) {
            $notification->setAmountValue($response['amount']['value']);
            $notification->setAmountCurrency($response['amount']['currency']);
        }
        if (isset($response['reason'])) {
            $notification->setReason($response['reason']);
        }

        $notification->setLive($notificationMode);

        if (isset($response['additionalData'])) {
            $notification->setAdditionalData($this->serializer->serialize($response['additionalData']));
        }
        if (isset($response['done'])) {
            $notification->setDone($response['done']);
        }

        // do this to set both fields in the correct timezone
        $date = new \DateTime();
        $notification->setCreatedAt($date);
        $notification->setUpdatedAt($date);

        $notification->save();

        return true;
    }

    /**
     * Check if remote IP address is from Adyen
     *
     * @return bool
     */
    protected function isIpValid()
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
    protected function isDuplicate($response)
    {
        $pspReference = trim($response['pspReference']);
        $eventCode = trim($response['eventCode']);
        $success = trim($response['success']);
        $originalReference = null;
        if (isset($response['originalReference'])) {
            $originalReference = trim($response['originalReference']);
        }
        $notification = $this->notificationFactory->create();
        return $notification->isDuplicate($pspReference, $eventCode, $success, $originalReference);
    }

    /**
     * Fix these global variables for the CGI
     */
    protected function fixCgiHttpAuthentication()
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
    protected function return401()
    {
        $this->getResponse()->setHttpResponseCode(401);
    }
}

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

use Adyen\Util\HmacSignature;
use Symfony\Component\Config\Definition\Exception\Exception;
use Magento\Framework\App\Request\Http as Http;

/**
 * Class Json extends Action
 */
class Json extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $_resultFactory;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

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
     * Json constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Adyen\Payment\Helper\Config $configHelper
     * @param \Adyen\Payment\Helper\IpAddress $ipAddressHelper
     * @param HmacSignature $hmacSignature
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Adyen\Payment\Helper\Config $configHelper,
        \Adyen\Payment\Helper\IpAddress $ipAddressHelper,
        HmacSignature $hmacSignature
    ) {
        parent::__construct($context);
        $this->_objectManager = $context->getObjectManager();
        $this->_resultFactory = $context->getResultFactory();
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->serializer = $serializer;
        $this->configHelper = $configHelper;
        $this->ipAddressHelper = $ipAddressHelper;
        $this->hmacSignature = $hmacSignature;

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
                ->setBody($this->_adyenHelper->getModuleVersion());

            return;
        }

        try {
            $notificationItems = json_decode(file_get_contents('php://input'), true);

            $notificationMode = isset($notificationItems['live']) ? $notificationItems['live'] : "";

            if ($notificationMode !== "" && $this->_validateNotificationMode($notificationMode)) {
                foreach ($notificationItems['notificationItems'] as $notificationItem) {
                    $status = $this->_processNotification(
                        $notificationItem['NotificationRequestItem'],
                        $notificationMode
                    );

                    if ($status != true) {
                        $this->_return401();
                        return;
                    }

                    $acceptedMessage = "[accepted]";
                }
                $cronCheckTest = $notificationItems['notificationItems'][0]['NotificationRequestItem']['pspReference'];

                // Run the query for checking unprocessed notifications, do this only for test notifications coming
                // from the Adyen Customer Area
                if ($this->_isTestNotification($cronCheckTest)) {
                    $unprocessedNotifications = $this->_adyenHelper->getUnprocessedNotifications();
                    if ($unprocessedNotifications > 0) {
                        $acceptedMessage .= "\nYou have " . $unprocessedNotifications . " unprocessed notifications.";
                    }
                }

                $this->_adyenLogger->addAdyenNotification("The result is accepted");

                $this->getResponse()
                    ->clearHeader('Content-Type')
                    ->setHeader('Content-Type', 'text/html')
                    ->setBody($acceptedMessage);
                return;
            } else {
                if ($notificationMode == "") {
                    $this->_return401();
                    return;
                }
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Mismatch between Live/Test modes of Magento store and the Adyen platform')
                );
            }
        } catch (Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @param $notificationMode
     * @return bool
     */
    protected function _validateNotificationMode($notificationMode)
    {
        $mode = $this->_adyenHelper->getAdyenAbstractConfigData('demo_mode');

        // Notification mode can be a string or a boolean
        if (($mode == '1' && ($notificationMode == "false" || !$notificationMode))
            || ($mode == '0' && ($notificationMode == 'true' || $notificationMode))) {
            return true;
        }
        return false;
    }

    /**
     * save notification into the database for cronjob to execute notification
     *
     * @param $response
     * @param $notificationMode
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _processNotification($response, $notificationMode)
    {
        //Validate if Ip check is enabled and if the notification comes from a verified IP
        if ($this->configHelper->getNotificationsIpCheck() && !$this->isIpValid()) {
            $this->_adyenLogger->addAdyenNotification(
                "Notification has been rejected because the IP address could not be verified"
            );
            return false;
        }
        if ($this->configHelper->getNotificationsHmacCheck() && $this->hmacSignature->isHmacSupportedEventCode(
                $response
            )) {
            //Validate the Hmac calculation
            if (!$this->hmacSignature->isValidNotificationHMAC(
                $this->configHelper->getNotificationsHmacKey(),
                $response
            )) {
                $this->_adyenLogger->addAdyenNotification(
                    'HMAC key validation failed ' . print_r($response, 1)
                );
                return false;
            }
        }
        // validate the notification
        if ($this->authorised($response)) {
            // log the notification
            $this->_adyenLogger->addAdyenNotification(
                "The content of the notification item is: " . print_r($response, 1)
            );

            // check if notification already exists
            if (!$this->_isDuplicate($response)) {
                try {
                    $notification = $this->_objectManager->create(\Adyen\Payment\Model\Notification::class);

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
                } catch (Exception $e) {
                    throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
                }
            } else {
                // duplicated so do nothing but return accepted to Adyen
                return true;
            }
        }
        return false;
    }

    /**
     * HTTP Authentication of the notification
     *
     * @param $response
     * @return bool
     */
    protected function authorised($response)
    {
        // Add CGI support
        $this->_fixCgiHttpAuthentication();

        $internalMerchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData('merchant_account');
        $username = $this->_adyenHelper->getAdyenAbstractConfigData('notification_username');
        $password = $this->_adyenHelper->getNotificationPassword();

        $submitedMerchantAccount = $response['merchantAccountCode'];

        if (empty($submitedMerchantAccount) && empty($internalMerchantAccount)) {
            if ($this->_isTestNotification($response['pspReference'])) {
                $this->_returnResult('merchantAccountCode is empty in magento settings');
            }
            return false;
        }

        // validate username and password
        if ((!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']))) {
            if ($this->_isTestNotification($response['pspReference'])) {
                $this->_returnResult(
                    'Authentication failed: PHP_AUTH_USER or PHP_AUTH_PW are empty. See Adyen Magento manual CGI mode'
                );
            }
            return false;
        }

        $usernameIsValid = hash_equals($username, $_SERVER['PHP_AUTH_USER']);
        $passwordIsValid = hash_equals($password, $_SERVER['PHP_AUTH_PW']);
        if ($usernameIsValid && $passwordIsValid) {
            return true;
        }

        // If notification is test check if fields are correct if not return error
        if ($this->_isTestNotification($response['pspReference'])) {
            $this->_returnResult(
                'username (PHP_AUTH_USER) and\or password (PHP_AUTH_PW) are not the same as Magento settings'
            );
        }
        return false;
    }

    /**
     * Checks if any of the possible remote IP address sending the notification is verified and returns the validation result
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
    protected function _isDuplicate($response)
    {
        $pspReference = trim($response['pspReference']);
        $eventCode = trim($response['eventCode']);
        $success = trim($response['success']);
        $originalReference = null;
        if (isset($response['originalReference'])) {
            $originalReference = trim($response['originalReference']);
        }
        $notification = $this->_objectManager->create(\Adyen\Payment\Model\Notification::class);
        return $notification->isDuplicate($pspReference, $eventCode, $success, $originalReference);
    }

    /**
     * Fix these global variables for the CGI
     */
    protected function _fixCgiHttpAuthentication()
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
    protected function _return401()
    {
        $this->getResponse()->setHttpResponseCode(401);
    }

    /**
     * If notification is a test notification from Adyen Customer Area
     *
     * @param $pspReference
     * @return bool
     */
    protected function _isTestNotification($pspReference)
    {
        if (strpos(strtolower($pspReference), "test_") !== false
            || strpos(strtolower($pspReference), "testnotification_") !== false
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the message to the browser
     *
     * @param $message
     */
    protected function _returnResult($message)
    {
        $this->getResponse()
            ->clearHeader('Content-Type')
            ->setHeader('Content-Type', 'text/html')
            ->setBody($message);
    }
}

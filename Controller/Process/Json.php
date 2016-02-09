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

use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Class Json
 * @package Adyen\Payment\Controller\Process
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
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        parent::__construct($context);
        $this->_objectManager = $context->getObjectManager();
        $this->_resultFactory = $context->getResultFactory();
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        try {
            $notificationItems = json_decode(file_get_contents('php://input'), true);

            // log the notification
            $this->_adyenLogger->addAdyenNotification("The content of the notification is: " . print_r($notificationItems,1));

            $notificationMode = isset($notificationItems['live']) ? $notificationItems['live'] : "";

            if($notificationMode != "" && $this->_validateNotificationMode($notificationMode))
            {
                foreach($notificationItems['notificationItems'] as $notificationItem)
                {
                    $status = $this->_processNotification($notificationItem['NotificationRequestItem'], $notificationMode);
                    if($status == "401"){
                        $this->_return401();
                        return;
                    }
                }

                $this->_adyenLogger->addAdyenNotification("The result is accepted");

                $this->getResponse()
                    ->clearHeader('Content-Type')
                    ->setHeader('Content-Type', 'text/html')
                    ->setBody("[accepted]");
                return;
            } else
            {
                if($notificationMode == "") {
                    $this->_return401();
                    return;
                }
                throw new \Magento\Framework\Exception\LocalizedException(__('Mismatch between Live/Test modes of Magento store and the Adyen platform'));
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
        if ($mode=='1' &&  $notificationMode == "false" || $mode=='0' &&  $notificationMode == 'true') {
            return true;
        }
        return false;
    }


    /**
     * $desc save notification into the database for cronjob to execute notificaiton
     * @param $response
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _processNotification($response, $notificationMode)
    {
        // validate the notification
        if($this->authorised($response))
        {
            // check if notificaiton already exists
            if(!$this->_isDuplicate($response)) {

                try {

                    $notification = $this->_objectManager->create('Adyen\Payment\Model\Notification');

                    if (isset($response['pspReference'])) {
                        $notification->setPspreference($response['pspReference']);
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
                        $notification->setAddtionalData(serialize($response['additionalData']));
                    }
                    if (isset($response['done'])) {
                        $notification->setDone($response['done']);
                    }

                    // do this to set both fields in the correct timezone
                    $date = new \DateTime();
                    $notification->setCreatedAt($date);
                    $notification->setUpdatedAt($date);

                    $notification->save();

                } catch (Exception $e) {
                    throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
                }
            }
        } else {
            return "401";
        }
    }


    /**
     * @desc HTTP Authentication of the notification
     * @param $response
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
            if(strtolower(substr($response['pspReference'],0,17)) == "testnotification_" || strtolower(substr($response['pspReference'],0,5)) == "test_") {
                echo 'merchantAccountCode is empty in magento settings'; exit();
            }
            return false;
        }

        // validate username and password
        if ((!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['PHP_AUTH_PW']))) {
            if(strtolower(substr($response['pspReference'],0,17)) == "testnotification_" || strtolower(substr($response['pspReference'],0,5)) == "test_") {
                echo 'Authentication failed: PHP_AUTH_USER and PHP_AUTH_PW are empty. See Adyen Magento manual CGI mode'; exit();
            }
            return false;
        }

        $accountCmp = !$this->_adyenHelper->getAdyenAbstractConfigDataFlag('multiple_merchants')
            ? strcmp($submitedMerchantAccount, $internalMerchantAccount)
            : 0;

        $usernameCmp = strcmp($_SERVER['PHP_AUTH_USER'], $username);
        $passwordCmp = strcmp($_SERVER['PHP_AUTH_PW'], $password);
        if ($accountCmp === 0 && $usernameCmp === 0 && $passwordCmp === 0) {
            return true;
        }

        // If notification is test check if fields are correct if not return error
        if(strtolower(substr($response['pspReference'],0,17)) == "testnotification_" || strtolower(substr($response['pspReference'],0,5)) == "test_") {
            if($accountCmp != 0) {
                echo 'MerchantAccount in notification is not the same as in Magento settings'; exit();
            } elseif($usernameCmp != 0 || $passwordCmp != 0) {
                echo 'username (PHP_AUTH_USER) and\or password (PHP_AUTH_PW) are not the same as Magento settings'; exit();
            }
        }

        return false;
    }


    /**
     * $desc if notification is already saved ignore it
     * @param $response
     * @return bool
     */
    protected function _isDuplicate($response)
    {
        $pspReference = trim($response['pspReference']);
        $eventCode = trim($response['eventCode']);
        $success = trim($response['success']);

        $notification = $this->_objectManager->create('Adyen\Payment\Model\Notification');
        return $notification->isDuplicate($pspReference, $eventCode, $success);
    }

    /**
     * Fix these global variables for the CGI
     */
    protected function _fixCgiHttpAuthentication() { // unsupported is $_SERVER['REMOTE_AUTHORIZATION']: as stated in manual :p
        if (isset($_SERVER['REDIRECT_REMOTE_AUTHORIZATION']) && $_SERVER['REDIRECT_REMOTE_AUTHORIZATION'] != '') { //pcd note: no idea who sets this
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode($_SERVER['REDIRECT_REMOTE_AUTHORIZATION']));
        } elseif(!empty($_SERVER['HTTP_AUTHORIZATION'])){ //pcd note: standard in magento?
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
        } elseif (!empty($_SERVER['REMOTE_USER'])) { //pcd note: when cgi and .htaccess modrewrite patch is executed
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['REMOTE_USER'], 6)));
        } elseif (!empty($_SERVER['REDIRECT_REMOTE_USER'])) { //pcd note: no idea who sets this
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['REDIRECT_REMOTE_USER'], 6)));
        }
    }

    /**
     *
     */
    protected function _return401()
    {

        $this->getResponse()->setHttpResponseCode(401);
    }
}
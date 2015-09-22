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

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

class Result extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    protected $_orderHistoryFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory
     * @param \Magento\Checkout\Model\Session $session
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
        \Magento\Checkout\Model\Session $session,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_orderFactory = $orderFactory;
        $this->_orderHistoryFactory = $orderHistoryFactory;
        $this->_session = $session;
        $this->_adyenLogger = $adyenLogger;
        parent::__construct($context);
    }

    public function execute()
    {
        $response = $this->getRequest()->getParams();
        $this->_adyenLogger->info(print_r($response, true));

        $result = $this->validateResponse($response);

        if ($result) {
            $session = $this->_session;
            $session->getQuote()->setIsActive(false)->save();
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_cancel($response);
            $this->_redirect('checkout/cart');
        }
    }

    protected function _cancel($response)
    {
        $session = $this->_session;

        // restore the quote
        $session->restoreQuote();

        $order = $this->_order;

        $this->_adyenHelper->cancelOrder($order);

        if(isset($response['authResult']) && $response['authResult'] == \Adyen\Payment\Model\Notification::CANCELLED) {
            $this->messageManager->addError(__('You have cancelled the order. Please try again'));
        } else {
            $this->messageManager->addError(__('Your payment failed, Please try again later'));
        }

    }

    protected function validateResponse($response)
    {
        $result = true;

        $this->_debugData['Step1'] = 'Processing ResultUrl';
        $storeId = null;

        if (empty($response)) {
            $this->_debugData['error'] = 'Response is empty, please check your webserver that the result url accepts parameters';
            throw new \Magento\Framework\Exception\LocalizedException(__('Response is empty, please check your webserver that the result url accepts parameters'));
        }

        // Log the results in log file and adyen_debug table
        $this->_debugData['response'] = $response;


        // authenticate result url
        $authStatus = $this->_authenticate($response);
        if (!$authStatus) {
            throw new \Magento\Framework\Exception\LocalizedException(__('ResultUrl authentification failure'));
        }

        $incrementId = $response['merchantReference'];

        if($incrementId) {
            $order = $this->_getOrder($incrementId);
            if ($order->getId()) {

                $this->_eventManager->dispatch('adyen_payment_process_resulturl_before', [
                    'order' => $order,
                    'adyen_response' => $response
                ]);
                if (isset($response['handled'])) {
                    return;
                }

                // set StoreId for retrieving debug log setting
                $storeId = $order->getStoreId();

                // update the order
                $result = $this->_validateUpdateOrder($order, $response);

                $this->_eventManager->dispatch('adyen_payment_process_resulturl_after', [
                    'order' => $order,
                    'adyen_response' => $response
                ]);

            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__('Order does not exists with increment_id: %s1', $incrementId));
            }
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('Empty merchantReference'));
        }
        return $result;
    }

    /**
     * @param $order
     * @param $params
     */
    protected function _validateUpdateOrder($order, $response)
    {
        $result = false;

        $this->_debugData['Step2'] = 'Updating the order';

        $authResult = $response['authResult'];
        $paymentMethod = isset($response['paymentMethod']) ? trim($response['paymentMethod']) : '';
        $pspReference = isset($response['pspReference']) ? trim($response['pspReference']) : '';

        $type = 'Adyen Result URL response:';
        $comment = __('%1 <br /> authResult: %2 <br /> pspReference: %3 <br /> paymentMethod: %4', $type, $authResult, $pspReference, $paymentMethod);

        $history = $this->_orderHistoryFactory->create()
            //->setStatus($status)
            ->setComment($comment)
            ->setEntityName('order')
            ->setOrder($order)
        ;

        $history->save();

        // needed  becuase then we need to save $order objects
        $order->setAdyenResulturlEventCode($authResult);


        switch ($authResult) {

            case \Adyen\Payment\Model\Notification::AUTHORISED:
            case \Adyen\Payment\Model\Notification::PENDING:
                // do nothing wait for the notification
                $result = true;
                $this->_debugData['Step4'] = 'Do nothing wait for the notification';
                break;
            case \Adyen\Payment\Model\Notification::CANCELLED:
                $this->_debugData['Step4'] = 'Cancel or Hold the order';
                $result = false;
                break;
            case \Adyen\Payment\Model\Notification::REFUSED:
                // if refused there will be a AUTHORIZATION : FALSE notification send only exception is ideal
                $this->_debugData['Step4'] = 'Cancel or Hold the order';
                $result = false;
                break;
            case \Adyen\Payment\Model\Notification::ERROR:
                //attempt to hold/cancel
                $this->_debugData['Step4'] = 'Cancel or Hold the order';
                $result = false;
                break;
            default:
                $this->_debugData['error'] = 'This event is not supported: ' . $authResult;
                $result = false;
                break;
        }

        return $result;
    }


    /**
     * @desc Authenticate using sha1 Merchant signature
     * @see success Action during checkout
     * @param Varien_Object $response
     */
    protected function _authenticate($response) {

        $hmacKey = $this->_adyenHelper->getHmac();

        // do not include the merchantSig in the merchantSig calculation
        $merchantSigNotification = $response['merchantSig'];
        unset($response['merchantSig']);

        // Sort the array by key using SORT_STRING order
        ksort($response, SORT_STRING);

        // Generate the signing data string
        $signData = implode(":",array_map(array($this, 'escapeString'),array_merge(array_keys($response), array_values($response))));

        $merchantSig = base64_encode(hash_hmac('sha256',$signData,pack("H*" , $hmacKey),true));


        if (strcmp($merchantSig, $merchantSigNotification) === 0) {
            return true;
        }
        return false;
    }

    /*
   * @desc The character escape function is called from the array_map function in _signRequestParams
   * $param $val
   * return string
   */
    protected function escapeString($val)
    {
        return str_replace(':','\\:',str_replace('\\','\\\\',$val));
    }

    protected function _getOrder($incrementId)
    {
        if (!$this->_order) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }

}
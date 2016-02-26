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

class ResultPos extends \Magento\Framework\App\Action\Action
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
        $this->_adyenLogger->addAdyenResult(print_r($response, true));

        $result = $this->_validateResponse($response);

        if ($result) {
            $session = $this->_session;
            $session->getQuote()->setIsActive(false)->save();
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_cancel($response);
            $this->_redirect('checkout/cart');
        }
    }

    private function _validateResponse($response)
    {

        $result = false;

        if($response != null && $response['result'] != "" && $this->_validateChecksum($response)) {

            $incrementId = $response['merchantReference'];
            $responseResult = $response['result'];

            if($incrementId) {
                $order = $this->_getOrder($incrementId);
                if ($order->getId()) {

                    $comment = __('%1 <br /> Result: %2 <br /> paymentMethod: %3', 'Adyen App Result URL Notification:', $responseResult, 'POS');

                    if($responseResult == 'APPROVED') {

                        $this->_adyenLogger->addAdyenResult('Result is approved');

                        $history = $this->_orderHistoryFactory->create()
                            //->setStatus($status)
                            ->setComment($comment)
                            ->setEntityName('order')
                            ->setOrder($order)
                        ;
                        $history->save();

                        // needed  becuase then we need to save $order objects
                        $order->setAdyenResulturlEventCode("POS_APPROVED");

                        // save order
                        $order->save();

                        return true;
                    } else {
                        $this->_adyenLogger->addAdyenResult('Result is:' . $responseResult);

                        $history = $this->_orderHistoryFactory->create()
                            //->setStatus($status)
                            ->setComment($comment)
                            ->setEntityName('order')
                            ->setOrder($order)
                        ;
                        $history->save();

                        // cancel the order
                        if ($order->canCancel()) {
                            $order->cancel()->save();
                            $this->_adyenLogger->addAdyenResult('Order is cancelled');
                        } else {
                            $this->_adyenLogger->addAdyenResult('Order can not be cancelled');
                        }
                    }
                } else {
                    $this->_adyenLogger->addAdyenResult('Order does not exists with increment_id: ' . $incrementId);
                }
            } else {
                $this->_adyenLogger->addAdyenResult('Empty merchantReference');
            }
        } else {
            $this->_adyenLogger->addAdyenResult('actionName or checksum failed or response is empty');
        }
        return $result;
    }

    protected function _validateChecksum($response)
    {
        $checksum = $response['cs'];
        $result = $response['result'];
        $amount = $response['originalCustomAmount'];
        $currency = $response['originalCustomCurrency'];
        $sessionId = $response['sessionId'];


        // for android sessionis is with low i
        if($sessionId == "") {
            $sessionId = $response['sessionid'];
        }

        // calculate amount checksum
        $amount_checksum = 0;

        $amountLength = strlen($amount);
        for($i=0;$i<$amountLength;$i++)
        {
            // ASCII value use ord
            $checksumCalc = ord($amount[$i]) - 48;
            $amount_checksum += $checksumCalc;
        }

        $currency_checksum = 0;
        $currencyLength = strlen($currency);
        for($i=0;$i<$currencyLength;$i++)
        {
            $checksumCalc = ord($currency[$i]) - 64;
            $currency_checksum += $checksumCalc;
        }

        $result_checksum = 0;
        $resultLength = strlen($result);
        for($i=0;$i<$resultLength;$i++)
        {
            $checksumCalc = ord($result[$i]) - 64;
            $result_checksum += $checksumCalc;
        }

        $sessionId_checksum = 0;
        $sessionIdLength = strlen($sessionId);
        for($i=0;$i<$sessionIdLength;$i++)
        {
            $checksumCalc = $this->_getAscii2Int($sessionId[$i]);
            $sessionId_checksum += $checksumCalc;
        }

        $total_result_checksum = (($amount_checksum + $currency_checksum + $result_checksum) * $sessionId_checksum) % 100;

        // check if request is valid
        if($total_result_checksum == $checksum) {
            $this->_adyenLogger->addAdyenResult('Checksum is valid');
            return true;
        }
        $this->_adyenLogger->addAdyenResult('Checksum is invalid!');
        return false;
    }

    protected function _getAscii2Int($ascii)
    {
        if (is_numeric($ascii)){
            $int = ord($ascii) - 48;
        } else {
            $int = ord($ascii) - 64;
        }
        return $int;
    }

    protected function _getOrder($incrementId)
    {
        if (!$this->_order) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }

    protected function _cancel($response)
    {
        $session = $this->_session;

        // restore the quote
        $session->restoreQuote();

        $order = $this->_order;

        if($order) {
            $this->_adyenHelper->cancelOrder($order);

            if(isset($response['authResult']) && $response['authResult'] == \Adyen\Payment\Model\Notification::CANCELLED) {
                $this->messageManager->addError(__('You have cancelled the order. Please try again'));
            } else {
                $this->messageManager->addError(__('Your payment failed, Please try again later'));
            }
        }
    }

}
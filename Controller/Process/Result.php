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

use \Adyen\Payment\Model\Notification;

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
     * Result constructor.
     *
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

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $response = $this->getRequest()->getParams();
        $this->_adyenLogger->addAdyenResult(print_r($response, true));

        if ($response) {
            $result = $this->validateResponse($response);

            if ($result) {
                $session = $this->_session;
                $session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success', ['utm_nooverride' => '1']);
            } else {
                $this->_cancel($response);
                $this->_redirect('checkout/cart');
            }
        } else {
            // redirect to checkout page
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * @param $response
     */
    protected function _cancel($response)
    {
        $session = $this->_session;

        // restore the quote
        $session->restoreQuote();

        $order = $this->_order;

        $this->_adyenHelper->cancelOrder($order);

        if (isset($response['authResult']) && $response['authResult'] == \Adyen\Payment\Model\Notification::CANCELLED) {
            $this->messageManager->addError(__('You have cancelled the order. Please try again'));
        } else {
            $this->messageManager->addError(__('Your payment failed, Please try again later'));
        }
    }

    /**
     * @param $response
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function validateResponse($response)
    {
        $result = true;

        $this->_adyenLogger->addAdyenResult('Processing ResultUrl');

        if (empty($response)) {
            $this->_adyenLogger->addAdyenResult(
                'Response is empty, please check your webserver that the result url accepts parameters'
            );

            throw new \Magento\Framework\Exception\LocalizedException(
                __('Response is empty, please check your webserver that the result url accepts parameters')
            );
        }

        // authenticate result url
        $authStatus = $this->_authenticate($response);
        if (!$authStatus) {
            throw new \Magento\Framework\Exception\LocalizedException(__('ResultUrl authentification failure'));
        }

        $incrementId = $response['merchantReference'];

        if ($incrementId) {
            $order = $this->_getOrder($incrementId);
            if ($order->getId()) {

                $this->_eventManager->dispatch('adyen_payment_process_resulturl_before', [
                    'order' => $order,
                    'adyen_response' => $response
                ]);
                if (isset($response['handled'])) {
                    return $response['handled_response'];
                }

                // update the order
                $result = $this->_validateUpdateOrder($order, $response);

                $this->_eventManager->dispatch('adyen_payment_process_resulturl_after', [
                    'order' => $order,
                    'adyen_response' => $response
                ]);

            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Order does not exists with increment_id: %1', $incrementId)
                );
            }
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Empty merchantReference')
            );
        }
        return $result;
    }

    /**
     * @param $order
     * @param $response
     * @return bool
     */
    protected function _validateUpdateOrder($order, $response)
    {
        $result = false;

        $this->_adyenLogger->addAdyenResult('Updating the order');

        $authResult = $response['authResult'];
        $paymentMethod = isset($response['paymentMethod']) ? trim($response['paymentMethod']) : '';
        $pspReference = isset($response['pspReference']) ? trim($response['pspReference']) : '';

        $type = 'Adyen Result URL response:';
        $comment = __('%1 <br /> authResult: %2 <br /> pspReference: %3 <br /> paymentMethod: %4',
            $type, $authResult, $pspReference, $paymentMethod
        );

        $history = $this->_orderHistoryFactory->create()
            //->setStatus($status)
            ->setComment($comment)
            ->setEntityName('order')
            ->setOrder($order)
        ;

        $history->save();

        // needed because then we need to save $order objects
        $order->setAdyenResulturlEventCode($authResult);

        switch ($authResult) {
            case Notification::AUTHORISED:
            case Notification::PENDING:
                // do nothing wait for the notification
                $result = true;
                $this->_adyenLogger->addAdyenResult('Do nothing wait for the notification');
                break;
            case Notification::CANCELLED:
                $this->_adyenLogger->addAdyenResult('Cancel or Hold the order');
                $result = false;
                break;
            case Notification::REFUSED:
                // if refused there will be a AUTHORIZATION : FALSE notification send only exception is idea
                $this->_adyenLogger->addAdyenResult('Cancel or Hold the order');
                $result = false;
                break;
            case Notification::ERROR:
                //attempt to hold/cancel
                $this->_adyenLogger->addAdyenResult('Cancel or Hold the order');
                $result = false;
                break;
            default:
                $this->_adyenLogger->addAdyenResult('This event is not supported: ' . $authResult);
                $result = false;
                break;
        }

        return $result;
    }
    
    /**
     * Authenticate using sha1 Merchant signature
     *
     * @param $response
     * @return bool
     */
    protected function _authenticate($response) {

        $hmacKey = $this->_adyenHelper->getHmac();
        $merchantSigNotification = $response['merchantSig'];

        // do it like this because $_GET is converting dot to underscore
        $queryString = $_SERVER['QUERY_STRING'];
        $result = [];
        $pairs = explode("&", $queryString);

        foreach ($pairs as $pair) {
            $nv = explode("=", $pair);
            $name = urldecode($nv[0]);
            $value = urldecode($nv[1]);
            $result[$name] = $value;
        }

        // do not include the merchantSig in the merchantSig calculation
        unset($result['merchantSig']);

        // Sort the array by key using SORT_STRING order
        ksort($result, SORT_STRING);

        // Generate the signing data string
        $signData = implode(":", array_map([$this, 'escapeString'],
            array_merge(array_keys($result), array_values($result))));

        $merchantSig = base64_encode(hash_hmac('sha256', $signData, pack("H*", $hmacKey), true));

        if (strcmp($merchantSig, $merchantSigNotification) === 0) {
            return true;
        }
        return false;
    }

    /**
     * The character escape function is called from the array_map function in _signRequestParams
     *
     * @param $val
     * @return mixed
     */
    protected function escapeString($val)
    {
        return str_replace(':','\\:',str_replace('\\','\\\\',$val));
    }

    /**
     * Get order based on increment_id
     *
     * @param $incrementId
     * @return \Magento\Sales\Model\Order
     */
    protected function _getOrder($incrementId)
    {
        if (!$this->_order) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }
}
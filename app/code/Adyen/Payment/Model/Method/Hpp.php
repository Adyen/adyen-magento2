<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Adyen\Payment\Model\Method;

use Magento\Framework\Object;
use Magento\Payment\Model\Method\ConfigInterface;
use Magento\Payment\Model\Method\Online\GatewayInterface;

/**
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Hpp extends \Magento\Payment\Model\Method\AbstractMethod implements GatewayInterface
{

    const METHOD_CODE = 'adyen_hpp';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;


    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_isInitializeNeeded = true;



    public function initialize($paymentAction, $stateObject)
    {

        $this->_logger->critical("initialize FROPM HPP Payment action is:". $paymentAction);

        $requestType = null;
        switch ($paymentAction) {
            case self::ACTION_AUTHORIZE:
                $requestType = self::REQUEST_TYPE_AUTH_ONLY;
            //intentional
            case self::ACTION_AUTHORIZE_CAPTURE:
//                $requestType = $requestType ?: self::REQUEST_TYPE_AUTH_CAPTURE;
                $payment = $this->getInfoInstance();
                $order = $payment->getOrder();
                $order->setCanSendNewEmailFlag(false);
                $payment->setBaseAmountAuthorized($order->getBaseTotalDue());
                $payment->setAmountAuthorized($order->getTotalDue());
//                $payment->setAnetTransType($requestType);
                break;
            default:
                break;
        }
//        magento 1.x code from our plugin
//        $state = Mage_Sales_Model_Order::STATE_NEW;
//        $stateObject->setState($state);
//        $stateObject->setStatus($this->_getConfigData('order_status'));
    }



    /**
     * Get config payment action url
     * Used to universalize payment actions when processing payment place
     *
     * @return string
     * @api
     */
//    public function getConfigPaymentAction()
//    {
//        // IMPORTANT need to set authorize_capture in config as well
//        $this->_logger->critical("TEST getConfigPaymentAction FROM HPP!!:");
//        return \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE_CAPTURE;
//    }


    /**
     * Checkout order place redirect URL getter
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $this->_logger->critical("getOrderPlaceRedirectUrl");
        $method = $this->getMethodInstance();
        if ($method) {
            $this->_logger->critical("getOrderPlaceRedirectUrl url is:" . $method->getConfigData('order_place_redirect_url'));
            return $method->getConfigData('order_place_redirect_url');
        } else {
            $this->_logger->critical("ELSE:");
            return "http://www.google.com/";
        }
        return '';
    }


    /**
     * Post request to gateway and return response
     *
     * @param Object $request
     * @param ConfigInterface $config
     *
     * @return Object
     *
     * @throws \Exception
     */
    public function postRequest(Object $request, ConfigInterface $config)
    {
        $this->_logger->critical("postRequest");
        // TODO: Implement postRequest() method.
    }
}
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

namespace Adyen\Payment\Block;

use Symfony\Component\Config\Definition\Exception\Exception;

class Redirect extends \Magento\Payment\Block\Form
{

    protected $_orderFactory;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var  \Magento\Checkout\Model\Order
     */
    protected $_order;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = [],
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->_orderFactory = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context, $data);
        $this->_getOrder();
    }


    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    public function getFormUrl()
    {
        $result = "";
        try {
            $order = $this->_order;
            if($order->getPayment())
            {
                $result = $this->_order->getPayment()->getMethodInstance()->getFormUrl();
            }
        } catch(Exception $e) {
            // do nothing for now
        }

        return $result;
    }

    public function test() {
        $orderId = $this->_checkoutSession->getId();
        $orderId = $this->_checkoutSession->getLastOrderId();
        return "incrementIdd: " . $orderId;
//        return "sdf";
    }

    public function getFormFields()
    {
        $result = array();
        try {
            if($this->_order->getPayment())
            {
                $result = $this->_order->getPayment()->getMethodInstance()->getFormFields();
            }

        } catch(Exception $e) {
            // do nothing for now
        }
        return $result;
    }


    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function _getOrder()
    {
        if (!$this->_order) {
            $incrementId = $this->_getCheckout()->getLastRealOrderId();
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }

    /**
     * Get frontend checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_checkoutSession;
    }
}
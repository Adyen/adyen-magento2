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
 * Copyright (c) 2018 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Info;

use Adyen\Payment\Helper\ChargedCurrency;
use Magento\Framework\View\Element\Template;

class PaymentLink extends AbstractInfo
{
    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Adyen\Payment\Gateway\Command\PayByMailCommand
     */
    private $payByMail;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $adyenOrderPaymentCollectionFactory,
        Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Adyen\Payment\Gateway\Command\PayByMailCommand $payByMailCommand,
        ChargedCurrency $chargedCurrency,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->payByMail = $payByMailCommand;
        $this->chargedCurrency = $chargedCurrency;

        parent::__construct($adyenHelper, $adyenOrderPaymentCollectionFactory, $context, $data);
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->registry->registry('current_order');
    }

    /**
     * @return \Magento\Sales\Model\Order\Payment
     */
    public function getPayment()
    {
        $order = $this->getOrder();

        return $order->getPayment();
    }

    /**
     * @return string
     */
    public function getPaymentLinkUrl()
    {
        $adyenAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($this->getOrder(), false);
        return $this->payByMail->generatePaymentUrl($this->getPayment(), $adyenAmountCurrency->getAmountDue());
    }

    /**
     * Check if order was placed using Adyen payment method
     * and if total due is greater than zero while one or more payments have been made
     *
     * @return string
     */
    public function _toHtml()
    {
        return strpos($this->getPayment()->getMethod(), 'adyen_') === 0
        && $this->_scopeConfig->getValue('payment/adyen_hpp/active')
        && $this->_scopeConfig->getValue('payment/adyen_pay_by_mail/active')
        && $this->getOrder()->getTotalDue() > 0
            ? parent::_toHtml()
            : '';
    }
}

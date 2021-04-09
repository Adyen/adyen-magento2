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

namespace Adyen\Payment\Block\Form;

use Adyen\Payment\Helper\ChargedCurrency;

class Oneclick extends \Adyen\Payment\Block\Form\Cc
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/oneclick.phtml';

    /**
     * @var \Magento\Backend\Model\Session\Quote
     */
    protected $_sessionQuote;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * Oneclick constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Backend\Model\Session\Quote $sessionQuote
     * @param ChargedCurrency $chargedCurrency
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Backend\Model\Session\Quote $sessionQuote,
        ChargedCurrency $chargedCurrency,
        array $data = []
    ) {
        parent::__construct($context, $paymentConfig, $adyenHelper, $checkoutSession, $data);
        $this->_sessionQuote = $sessionQuote;
        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * @return array
     */
    public function getOneClickCards()
    {
        $customerId = $this->_sessionQuote->getCustomerId();
        $storeId = $this->_sessionQuote->getStoreId();
        $grandTotal = $this->chargedCurrency->getQuoteAmountCurrency($this->_sessionQuote->getQuote())->getAmount();


        // For backend only allow recurring payments
        $recurringType = \Adyen\Payment\Model\RecurringType::RECURRING;

        $cards = $this->adyenHelper->getOneClickPaymentMethods($customerId, $storeId, $grandTotal, $recurringType);

        return $cards;
    }
}

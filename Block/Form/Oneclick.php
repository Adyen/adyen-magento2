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
use Adyen\Payment\Helper\Installments;
use Adyen\Payment\Logger\AdyenLogger;

class Oneclick extends \Magento\Payment\Block\Form
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
    protected $chargedCurrency;

    /**
     * Oneclick constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Backend\Model\Session\Quote $sessionQuote
     * @param Installments $installmentsHelper
     * @param ChargedCurrency $chargedCurrency
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Backend\Model\Session\Quote $sessionQuote,
        \Magento\Backend\Model\Session\Quote $backendCheckoutSession,
        Installments $installmentsHelper,
        ChargedCurrency $chargedCurrency,
        AdyenLogger $adyenLogger,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );
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

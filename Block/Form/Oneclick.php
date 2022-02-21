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
 * Copyright (c) 2021 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Form;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Magento\Backend\Model\Session\Quote;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Form;

class Oneclick extends Form
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/oneclick.phtml';

    /**
     * @var Quote
     */
    protected $_sessionQuote;

    /**
     * @var ChargedCurrency
     */
    protected $chargedCurrency;
    /**
     * @var Data
     */
    private $adyenHelper;

    public function __construct(
        Context $context,
        Quote $sessionQuote,
        ChargedCurrency $chargedCurrency,
        Data $adyenHelper,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );
        $this->_sessionQuote = $sessionQuote;
        $this->chargedCurrency = $chargedCurrency;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @return array
     */
    public function getOneClickCards()
    {
        $customerId = $this->_sessionQuote->getCustomerId();
        $storeId = $this->_sessionQuote->getStoreId();
        $grandTotal = $this->chargedCurrency->getQuoteAmountCurrency($this->_sessionQuote->getQuote())->getAmount();

        return $this->adyenHelper->getOneClickPaymentMethods($customerId, $storeId, $grandTotal);
    }
}

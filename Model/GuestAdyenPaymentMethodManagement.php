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

namespace Adyen\Payment\Model;

class GuestAdyenPaymentMethodManagement implements \Adyen\Payment\Api\GuestAdyenPaymentMethodManagementInterface
{
    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $_quoteIdMaskFactory;

    /**
     * @var \Adyen\Payment\Helper\PaymentMethods
     */
    protected $_paymentMethodsHelper;

    /**
     * GuestAdyenPaymentMethodManagement constructor.
     *
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Adyen\Payment\Helper\PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Adyen\Payment\Helper\PaymentMethods $paymentMethodsHelper
    ) {
        $this->_quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->_paymentMethodsHelper = $paymentMethodsHelper;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentMethods($cartId, \Magento\Quote\Api\Data\AddressInterface $shippingAddress = null)
    {
        $quoteIdMask = $this->_quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        // if shippingAddress is provided use this country
        $country = null;
        if ($shippingAddress) {
            $country = $shippingAddress->getCountryId();
        }

        return $this->_paymentMethodsHelper->getPaymentMethods($quoteId, $country);
    }
}

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

class AdyenPaymentMethodManagement implements \Adyen\Payment\Api\AdyenPaymentMethodManagementInterface
{
    /**
     * @var \Adyen\Payment\Helper\PaymentMethods
     */
    protected $_paymentMethodsHelper;
    protected $_quoteRepo;

    /**
     * AdyenPaymentMethodManagement constructor.
     *
     * @param \Adyen\Payment\Helper\PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
         \Magento\Quote\Api\CartRepositoryInterface $quoteRepo,    
        \Adyen\Payment\Helper\PaymentMethods $paymentMethodsHelper
    ) {
        $this->_paymentMethodsHelper = $paymentMethodsHelper;
        $this->_quoteRepo = $quoteRepo;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentMethods($customerId, \Magento\Quote\Api\Data\AddressInterface $shippingAddress = null)
    {
        // if shippingAddress is provided use this country
        $quote = $this->_quoteRepo->getActiveForCustomer($customerId);
        $quoteId = $quote->getId();
        
        $country = null;
        if ($shippingAddress) {
            $country = $shippingAddress->getCountryId();
        }

        return $this->_paymentMethodsHelper->getPaymentMethods($quoteId, $country, $includeSchemeType = true);
    }
}

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

    /**
     * AdyenPaymentMethodManagement constructor.
     *
     * @param \Adyen\Payment\Helper\PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\PaymentMethods $paymentMethodsHelper
    ) {
        $this->_paymentMethodsHelper = $paymentMethodsHelper;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentMethods($cartId, \Magento\Quote\Api\Data\AddressInterface $shippingAddress = null)
    {
        // if shippingAddress is provided use this country
        $country = null;
        if ($shippingAddress) {
            $country = $shippingAddress->getCountryId();
        }

        return $this->_paymentMethodsHelper->getPaymentMethods($cartId, $country);
    }
}

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

namespace Adyen\Payment\Api;

/**
 * Interface GuestAdyenPaymentMethodManagementInterface
 *
 * @api
 */
interface GuestAdyenPaymentMethodManagementInterface
{
    /**
     * Get payment information
     *
     * @param string $cartId
     * @param null|\Magento\Quote\Api\Data\AddressInterface
     * @return \Magento\Checkout\Api\Data\PaymentDetailsInterface
     */
    public function getPaymentMethods($cartId, \Magento\Quote\Api\Data\AddressInterface $shippingAddress = null);
}

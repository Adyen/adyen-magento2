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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api;

use Magento\Quote\Api\Data\AddressInterface;

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
     * @param null|AddressInterface
     * @return \Magento\Checkout\Api\Data\PaymentDetailsInterface
     */
    public function getPaymentMethods($cartId, AddressInterface $shippingAddress = null);
}

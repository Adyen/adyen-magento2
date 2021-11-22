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

namespace Adyen\Payment\Api\Internal;

use Magento\Quote\Api\Data\AddressInterface;

/**
 * Interface for fetching payment methods from Adyen for guest customers
 *
 * @api
 */
interface InternalGuestAdyenPaymentMethodManagementInterface
{
    /**
     * Fetches Adyen payment methods for guest customers
     *
     * @param string $cartId The ID of the cart/quote.
     * @param string $formKey Frontend form key.
     * @param AddressInterface|null $shippingAddress Shipping address to use for fetching the payment methods from Adyen.
     * @return \Magento\Checkout\Api\Data\PaymentDetailsInterface
     */
    public function handleInternalRequest($cartId, $formKey, AddressInterface $shippingAddress = null);
}

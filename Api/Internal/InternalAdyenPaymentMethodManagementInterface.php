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
 * Interface for fetching payment methods from Adyen for logged in customers
 *
 * @api
 */
interface InternalAdyenPaymentMethodManagementInterface
{
    /**
     * Fetches Adyen payment methods for logged in customers
     *
     * @param string $cartId The ID of the cart.
     * @param string $formKey Frontend form key.
     * @param AddressInterface|null $shippingAddress
     * @return \Magento\Checkout\Api\Data\PaymentDetailsInterface
     */
    public function handleInternalRequest($cartId, $formKey, AddressInterface $shippingAddress = null);
}

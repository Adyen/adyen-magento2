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

use Magento\Quote\Api\Data\AddressInterface;

/**
 * Interface InternalGuestAdyenPaymentMethodManagementInterface
 * This should only be called internally via ajax
 *
 * @api
 */
interface InternalGuestAdyenPaymentMethodManagementInterface
{
    /**
     * Handle the internal request by checking if it is internal and then calling the original interface
     *
     * @param string $cartId
     * @param null|AddressInterface
     * @return \Magento\Checkout\Api\Data\PaymentDetailsInterface
     */
    public function handleInternalRequest($cartId, AddressInterface $shippingAddress = null);
}

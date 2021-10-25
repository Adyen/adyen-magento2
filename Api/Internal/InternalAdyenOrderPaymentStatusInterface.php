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

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Interface InternalAdyenOrderPaymentStatusInterface
 * This should only be called internally via ajax
 *
 * @api
 */
interface InternalAdyenOrderPaymentStatusInterface
{
    /**
     * Handle the internal request by checking if it is internal and then calling the original interface
     *
     * @param string $orderId
     * @param string $shopperEmail
     * @param string $formKey
     * @return PaymentDetailsInterface
     */
    public function handleInternalRequest($orderId, $shopperEmail, $formKey);
}

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
 * Interface InternalAdyenPaymentDetailsInterface
 * This should only be called internally via ajax
 *
 * @api
 */
interface InternalAdyenPaymentDetailsInterface
{
    /**
     * Handle the internal request by checking if it is internal and then calling the original interface
     *
     * @param string $payload
     * @param string $formKey
     * @return PaymentDetailsInterface
     */
    public function handleInternalRequest($payload, $formKey);
}

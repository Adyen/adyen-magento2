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
 * Adyen Payment Module
 *
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api;

interface AdyenInitiateTerminalApiInterface
{
    /**
     * Trigger sync call on terminal for the shop front.
     *
     * @param string $payload
     * @return mixed
     */
    public function shopFrontInitiate($payload);

    /**
     * Trigger sync call on terminal for the cart api.
     *
     * @param int    $cartId
     * @param string $payload
     * @return mixed
     */
    public function apiCartInitiate($cartId, $payload);

    /**
     * Trigger sync call on terminal for the guest cart api.
     *
     * @param string $cartId
     * @param string $payload
     * @return mixed
     */
    public function apiGuestCartInitiate($cartId, $payload);
}

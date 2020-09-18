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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api;

use Magento\Sales\Model\Order;

interface AdyenThreeDSProcessInterface
{
    const AUTHORIZED = "AUTHORIZED";
    const UNSUCCESSFUL = "UNSUCCESSFUL";
    const ALREADY_SUCCESSFUL = "ALREADY_SUCCESSFUL";
    const NEEDS_REDIRECT = "NEEDS_REDIRECT";

    /**
     * Shared authorization logic across all frontend (native or PWA)
     * for handling a redirect after 3DS auth
     *
     * @param \Magento\Sales\Model\Order $order
     * @param string $requestMD
     * @param string $requestPaRes
     * @return string
     * @api
     */
    public function authorize(Order $order, $requestMD, $requestPaRes): string;

}

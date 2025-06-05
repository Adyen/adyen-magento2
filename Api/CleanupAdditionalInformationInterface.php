<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api;

use Magento\Sales\Api\Data\OrderPaymentInterface;

interface CleanupAdditionalInformationInterface
{
    const FIELD_ACTION = 'action';
    const FIELD_ADDITIONAL_DATA = 'additionalData';

    const FIELDS_TO_BE_CLEANED_UP = [
        self::FIELD_ACTION,
        self::FIELD_ADDITIONAL_DATA
    ];

    /**
     * @param OrderPaymentInterface $orderPayment
     * @return OrderPaymentInterface
     */
    public function execute(OrderPaymentInterface $orderPayment): OrderPaymentInterface;
}

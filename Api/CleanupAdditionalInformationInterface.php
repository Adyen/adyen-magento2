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
    const FIELD_DONATION_TOKEN = 'donationToken';
    const FIELD_FRONTEND_TYPE = 'frontendType';
    const FIELD_COMBO_CARD_TYPE = 'combo_card_type';
    const FIELD_CC_TYPE = 'cc_type';

    const FIELDS_TO_BE_CLEANED_UP = [
        self::FIELD_ACTION,
        self::FIELD_ADDITIONAL_DATA,
        self::FIELD_DONATION_TOKEN,
        self::FIELD_FRONTEND_TYPE,
        self::FIELD_COMBO_CARD_TYPE,
        self::FIELD_CC_TYPE
    ];

    /**
     * @param OrderPaymentInterface $orderPayment
     * @return OrderPaymentInterface
     */
    public function execute(OrderPaymentInterface $orderPayment): OrderPaymentInterface;
}

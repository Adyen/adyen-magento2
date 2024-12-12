<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\InstantPurchase\Card;

use Adyen\Payment\Helper\Data;
use InvalidArgumentException;
use Magento\InstantPurchase\PaymentMethodIntegration\PaymentTokenFormatterInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Adyen stored card formatter.
 */
class TokenFormatter implements PaymentTokenFormatterInterface
{
    public function __construct(
        protected readonly Data $adyenHelper
    ) { }

    public function formatPaymentToken(PaymentTokenInterface $paymentToken): string
    {
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);

        if (!isset($details['type'], $details['maskedCC'], $details['expirationDate'])) {
            throw new InvalidArgumentException('Invalid Adyen card token details.');
        }

        $ccTypes = $this->adyenHelper->getAdyenCcTypes();
        $typeArrayIndex = array_search($details['type'], array_column($ccTypes, 'code_alt'));

        if (is_int($typeArrayIndex)) {
            $ccType = $ccTypes[array_keys($ccTypes)[$typeArrayIndex]]['name'];
        } else {
            $ccType = $details['type'];
        }

        return sprintf(
            '%s: %s, %s: %s (%s: %s)',
            __('Card'),
            $ccType,
            __('ending'),
            $details['maskedCC'],
            __('expires'),
            $details['expirationDate']
        );
    }
}

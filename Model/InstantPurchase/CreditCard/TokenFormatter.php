<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\InstantPurchase\CreditCard;

use Adyen\Payment\Model\InstantPurchase\AbstractAdyenTokenFormatter;
use Magento\InstantPurchase\PaymentMethodIntegration\PaymentTokenFormatterInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Adyen stored credit card formatter.
 */
class TokenFormatter extends AbstractAdyenTokenFormatter implements PaymentTokenFormatterInterface
{

    public function formatPaymentToken(PaymentTokenInterface $paymentToken): string
    {
        return $this->formatCardPaymentToken($paymentToken);
    }
}

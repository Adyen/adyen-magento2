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

namespace Adyen\Payment\Model\InstantPurchase\PaymentMethods;

use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodFactory;
use Magento\InstantPurchase\PaymentMethodIntegration\PaymentTokenFormatterInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Adyen stored credit card formatter.
 */
class TokenFormatter implements PaymentTokenFormatterInterface
{
    /** @var PaymentMethodFactory */
    private $paymentMethodFactory;

    public function __construct(PaymentMethodFactory $paymentMethodFactory)
    {
        $this->paymentMethodFactory = $paymentMethodFactory;
    }

    public function formatPaymentToken(PaymentTokenInterface $paymentToken): string
    {
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        try {
            $adyenPaymentMethod = $this->paymentMethodFactory::createAdyenPaymentMethod($details['type']);
        } catch (PaymentMethodException $e) {
            return 'Unknown Payment Method';
        }

        return $adyenPaymentMethod->getLabel();
    }
}

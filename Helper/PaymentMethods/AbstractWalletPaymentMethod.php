<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\PaymentMethods;

abstract class AbstractWalletPaymentMethod implements PaymentMethodInterface
{
    /** @var string */
    private $cardScheme;

    abstract public function getTxVariant(): string;

    abstract public function getPaymentMethodName(): string;

    abstract public function supportsCardOnFile(): bool;

    abstract public function supportsSubscription(): bool;

    abstract public function supportsManualCapture(): bool;

    abstract public function supportsAutoCapture(): bool;

    abstract public function supportsUnscheduledCardOnFile(): bool;

    public function __construct(?string $cardScheme)
    {
        $this->cardScheme = $cardScheme;
    }

    public function getCardScheme(): string
    {
        return $this->cardScheme;
    }

    public function setCardScheme(string $cardScheme): void
    {
        $this->cardScheme = $cardScheme;
    }
}

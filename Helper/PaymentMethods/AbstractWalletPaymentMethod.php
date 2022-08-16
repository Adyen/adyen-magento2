<?php

namespace Adyen\Payment\Helper\PaymentMethods;

abstract class AbstractWalletPaymentMethod implements PaymentMethodInterface
{
    /** @var string */
    private $cardScheme;

    public abstract function getTxVariant(): string;

    public abstract function getPaymentMethodName(): string;

    public abstract function supportsCardOnFile(): bool;

    public abstract function supportsSubscription(): bool;

    public abstract function supportsManualCapture(): bool;

    public abstract function supportsAutoCapture(): bool;

    public abstract function supportsUnscheduledCardOnFile(): bool;

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

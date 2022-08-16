<?php

namespace Adyen\Payment\Helper\PaymentMethods;

class TxVariant
{
    /** @var string */
    private $card;

    /** @var string */
    private $paymentMethod;

    public function __construct(string $txVariant)
    {
        $splitVariant = explode('_', $txVariant, 2);
        if (count($splitVariant) > 1) {
            $this->card = $splitVariant[0];
            $this->paymentMethod = $splitVariant[1];
        } else {
            $this->paymentMethod = $splitVariant[0];
        }
    }

    public function getCard(): ?string
    {
        return $this->card;
    }

    public function setCard(?string $card): void
    {
        $this->card = $card;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function isWalletVariant(): bool
    {
        return isset($this->card);
    }
}

<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 * @deprecated Use {@see \Adyen\Payment\Model\Method\ValidatedTxVariant} instead.
 */

namespace Adyen\Payment\Model\Method;

class TxVariant
{
    private ?string $card = null;
    private string $paymentMethod;

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

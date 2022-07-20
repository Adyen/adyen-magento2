<?php


namespace Adyen\Payment\Helper\PaymentMethods;


class AmazonPayPaymentMethod implements PaymentMethodInterface
{
    const TX_VARIANT = 'amazonpay';
    const NAME = 'Amazon Pay';

    public function getTxVariant(): string
    {
        return self::TX_VARIANT;
    }

    public function getPaymentMethodName(): string
    {
        return self::NAME;
    }

    public function supportsManualCapture(): bool
    {
        return true;
    }

    public function supportsAutoCapture(): bool
    {
        return true;
    }

    public function supportsCardOnFile(): bool
    {
        return true;
    }

    public function supportsSubscription(): bool
    {
        return true;
    }

    public function isWalletPaymentMethod(): bool
    {
        return true;
    }

    public function supportsUnscheduledCardOnFile(): bool
    {
        return true;
    }
}
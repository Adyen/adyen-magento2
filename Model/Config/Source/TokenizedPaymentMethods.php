<?php

namespace Adyen\Payment\Model\Config\Source;

use Adyen\Payment\Model\Methods\Applepay;
use Adyen\Payment\Model\Methods\GooglePay;
use Adyen\Payment\Model\Methods\Klarna;
use Adyen\Payment\Model\Methods\Paypal;
use Magento\Framework\Data\OptionSourceInterface;

class TokenizedPaymentMethods implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [
            /** TODO: These PMs can be enabled for recurring purposes once tested */
            [
                'value' => Applepay::TX_VARIANT,
                'label' => Applepay::NAME
            ],
            /*[
                'value' => PaymentMethods\AmazonPayPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\AmazonPayPaymentMethod::NAME
            ],*/
            [
                'value' => GooglePay::TX_VARIANT,
                'label' => GooglePay::NAME
            ],
            [
                'value' => Paypal::TX_VARIANT,
                'label' => Paypal::NAME
            ],
            /*[
                'value' => PaymentMethods\SepaPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\SepaPaymentMethod::NAME
            ],
            [
                'value' => PaymentMethods\TwintPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\TwintPaymentMethod::NAME
            ],*/
            [
                'value' => Klarna::TX_VARIANT,
                'label' => Klarna::NAME
            ]
        ];
    }
}

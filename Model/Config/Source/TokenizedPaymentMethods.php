<?php

namespace Adyen\Payment\Model\Config\Source;

use Adyen\Payment\Helper\PaymentMethods;
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
            /*[
                'value' => PaymentMethods\ApplePayPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\ApplePayPaymentMethod::NAME
            ],
            [
                'value' => PaymentMethods\AmazonPayPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\AmazonPayPaymentMethod::NAME
            ],*/
            [
                'value' => PaymentMethods\GooglePayPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\GooglePayPaymentMethod::NAME
            ],
            [
                'value' => PaymentMethods\PayPalPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\PayPalPaymentMethod::NAME
            ],
            [
                'value' => PaymentMethods\SepaPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\SepaPaymentMethod::NAME
            ],
            [
                'value' => PaymentMethods\TwintPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\TwintPaymentMethod::NAME
            ],
            [
                'value' => PaymentMethods\KlarnaPayLaterPaymentMethod::TX_VARIANT,
                'label' => PaymentMethods\KlarnaPayLaterPaymentMethod::NAME
            ]
        ];
    }
}

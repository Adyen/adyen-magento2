<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\GiftcardPayment;
use Adyen\Payment\Helper\PaymentMethods;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\Helper\Data as PricingData;

class AdyenGiftcardConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_giftcard';
    const PAYMENT_METHOD_ICON = 'giftcard';

    private CheckoutSession $checkoutSession;
    private Data $adyenHelper;
    private GiftcardPayment $giftcardPaymentHelper;
    private PricingData $pricingDataHelper;
    private PaymentMethods $paymentMethodsHelper;

    public function __construct(
        CheckoutSession $checkoutSession,
        Data $adyenHelper,
        GiftcardPayment $giftcardPaymentHelper,
        PricingData $pricingDataHelper,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->giftcardPaymentHelper = $giftcardPaymentHelper;
        $this->pricingDataHelper = $pricingDataHelper;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    public function getConfig(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $currency = $quote->getQuoteCurrencyCode();
        $formattedOrderAmount = $this->adyenHelper->formatAmount(
            $quote->getGrandTotal(),
            $currency
        );

        $config['payment']['adyen']['giftcard']['icon'] =
            $this->paymentMethodsHelper->buildPaymentMethodIcon(self::PAYMENT_METHOD_ICON, []);

        $config['payment']['adyen']['giftcard']['quoteAmount'] = $formattedOrderAmount;
        $config['payment']['adyen']['giftcard']['currency'] = $currency;

        $giftcardDiscount = $this->giftcardPaymentHelper->getQuoteGiftcardDiscount($quote);
        $hasRedeemedGiftcard = $giftcardDiscount > 0;

        $config['payment']['adyen']['giftcard']['isRedeemed'] = $hasRedeemedGiftcard;

        if ($hasRedeemedGiftcard) {
            $totalDiscount = $this->adyenHelper->originalAmount(
                $giftcardDiscount,
                $currency
            );
            $remainingOrderAmount = $this->adyenHelper->originalAmount(
                $formattedOrderAmount - $giftcardDiscount,
                $currency
            );

            $config['payment']['adyen']['giftcard']['totalDiscount'] = $this->pricingDataHelper->currency(
                $totalDiscount,
                $currency,
                false
            );

            $config['payment']['adyen']['giftcard']['remainingOrderAmount'] = $this->pricingDataHelper->currency(
                $remainingOrderAmount,
                $currency,
                false
            );
        }

        return $config;
    }
}

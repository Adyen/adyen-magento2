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
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class AdyenGiftcardConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_giftcard';

    private CheckoutSession $checkoutSession;
    private Data $adyenHelper;

    public function __construct(
        CheckoutSession $checkoutSession,
        Data $adyenHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
    }

    public function getConfig()
    {
        $config['payment']['adyenGiftcard']['amount'] = $this->adyenHelper->formatAmount(
            $this->checkoutSession->getQuote()->getGrandTotal(),
            $this->checkoutSession->getQuote()->getQuoteCurrencyCode()
        );
        $config['payment']['adyenGiftcard']['currency'] = $this->checkoutSession->getQuote()->getQuoteCurrencyCode();

        return $config;
    }
}

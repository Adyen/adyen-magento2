<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\GuestAdyenPaymentMethodManagementInterface;
use Adyen\Payment\Helper\PaymentMethods;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestAdyenPaymentMethodManagement implements GuestAdyenPaymentMethodManagementInterface
{
    protected QuoteIdMaskFactory $quoteIdMaskFactory;

    protected PaymentMethods $paymentMethodsHelper;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    /**
     * @param string $cartId
     * @param string|null $shopperLocale
     * @return string
     */
    public function getPaymentMethods(string $cartId, ?string $shopperLocale = null): string {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        return $this->paymentMethodsHelper->getPaymentMethods($quoteId, $shopperLocale);
    }
}

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

namespace Adyen\Payment\Model\Api;

use Adyen\AdyenException;
use Adyen\Payment\Api\GuestAdyenPaymentMethodManagementInterface;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GuestAdyenPaymentMethodManagement implements GuestAdyenPaymentMethodManagementInterface
{
    /**
     * @param PaymentMethods $paymentMethodsHelper
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param string $cartId
     * @param string|null $shopperLocale
     * @param string|null $country
     * @param string|null $channel
     * @return string
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getPaymentMethods(
        string $cartId,
        ?string $shopperLocale = null,
        ?string $country = null,
        ?string $channel = null
    ): string {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error(sprintf("Quote with masked ID %s not found!", $cartId));
            throw new AdyenException(__('Error with payment method please select different payment method.'));
        }

        return $this->paymentMethodsHelper->getPaymentMethods($quoteId, $country, $shopperLocale, $channel);
    }
}

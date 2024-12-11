<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\InstantPurchase\PaymentMethods;

use Adyen\Payment\Api\InstantPurchase\PaymentMethodIntegration\AdyenAvailabilityCheckerInterface;
use Adyen\Payment\Helper\Vault;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Polyfill\Intl\Icu\Exception\MethodNotImplementedException;

class AvailabilityChecker implements AdyenAvailabilityCheckerInterface
{
    /**
     * @param Vault $vaultHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Vault $vaultHelper,
        private readonly StoreManagerInterface $storeManager
    ) { }

    /**
     * Instant Purchase is available if payment method recurring is enabled and
     * recurring processing model is set to `CardOnFile`.
     */
    public function isAvailableAdyenMethod(string $paymentMethodCode): bool
    {
        $storeId = $this->storeManager->getStore()->getId();

        $isMethodRecurringEnabled = $this->vaultHelper->getPaymentMethodRecurringActive(
            $paymentMethodCode,
            $storeId
        );
        $recurringProcessingModel = $this->vaultHelper->getPaymentMethodRecurringProcessingModel(
            $paymentMethodCode,
            $storeId
        );

        return $isMethodRecurringEnabled && $recurringProcessingModel === Vault::CARD_ON_FILE;
    }

    public function isAvailable(): bool
    {
        /*
         * This is the pseudo implementation of the interface. Actual logic has been written
         * in `isAvailableAdyenMethod() and implemented via plugin `InstantPurchaseIntegrationTest`.
         */
        throw new MethodNotImplementedException('isAvailable');
    }
}

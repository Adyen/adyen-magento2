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

namespace Adyen\Payment\Model\InstantPurchase\Card;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Magento\InstantPurchase\PaymentMethodIntegration\AvailabilityCheckerInterface;
use Magento\Store\Model\StoreManagerInterface;

class AvailabilityChecker implements AvailabilityCheckerInterface
{
    /**
     * @param Config $configHelper
     * @param Vault $vaultHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Config $configHelper,
        private readonly Vault $vaultHelper,
        private readonly StoreManagerInterface $storeManager
    ) { }

    /**
     * Instant Purchase is available if card recurring is enabled, recurring processing model is set to `CardOnFile`
     * and CVC is not required to complete the payment.
     */
    public function isAvailable(): bool
    {
        $storeId = $this->storeManager->getStore()->getId();

        $isCardRecurringEnabled = $this->vaultHelper->getPaymentMethodRecurringActive(
            AdyenCcConfigProvider::CODE,
            $storeId
        );

        $recurringProcessingModel = $this->vaultHelper->getPaymentMethodRecurringProcessingModel(
            AdyenCcConfigProvider::CODE,
            $storeId
        );

        $isCvcRequiredForCardRecurringPayments =
            $this->configHelper->getIsCvcRequiredForRecurringCardPayments($storeId);

        return $isCardRecurringEnabled &&
            !$isCvcRequiredForCardRecurringPayments &&
            $recurringProcessingModel === Vault::CARD_ON_FILE;
    }
}

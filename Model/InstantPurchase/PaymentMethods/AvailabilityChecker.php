<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\InstantPurchase\PaymentMethods;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Recurring;
use Magento\InstantPurchase\PaymentMethodIntegration\AvailabilityCheckerInterface;

class AvailabilityChecker implements AvailabilityCheckerInterface
{
    /** @var Config */
    private $configHelper;

    public function __construct(Config $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * Check if store alternative payment methods is set to true AND the type is set to CardOnFile
     *
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        $storeVaultPmEnabled = $this->configHelper->isStoreAlternativePaymentMethodEnabled();
        $vaultPmTokenType = $this->configHelper->getAlternativePaymentMethodTokenType() === Recurring::CARD_ON_FILE;

        return $storeVaultPmEnabled && $vaultPmTokenType;
    }
}

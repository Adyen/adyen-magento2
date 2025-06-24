<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Plugin;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Api\InstantPurchase\PaymentMethodIntegration\AdyenAvailabilityCheckerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InstantPurchase\PaymentMethodIntegration\Integration;
use Magento\Payment\Helper\Data;

class InstantPurchaseIntegration
{
    /**
     * @param AdyenAvailabilityCheckerInterface $adyenAvailabilityChecker
     * @param PaymentMethods $paymentMethodsHelper
     * @param Data $paymentDataHelper
     */
    public function __construct(
        protected readonly AdyenAvailabilityCheckerInterface $adyenAvailabilityChecker,
        protected readonly PaymentMethods                    $paymentMethodsHelper,
        protected readonly Data                              $paymentDataHelper
    ) { }

    /**
     * @param Integration $subject
     * @param callable $proceed
     * @return bool
     * @throws LocalizedException
     */
    public function aroundIsAvailable(Integration $subject, callable $proceed): bool
    {
        $vaultPaymentMethodInstance = $subject->getPaymentMethod();
        $providerMethodCode = $vaultPaymentMethodInstance->getProviderCode();

        $providerMethodInstance = $this->paymentDataHelper->getMethodInstance(
            $providerMethodCode
        );
        $isAdyenAlternativePaymentMethod = $this->paymentMethodsHelper->isAlternativePaymentMethod(
            $providerMethodInstance
        );
        $isAdyenWalletPaymentMethod = $this->paymentMethodsHelper->isWalletPaymentMethod(
            $providerMethodInstance
        );

        if ($isAdyenAlternativePaymentMethod && !$isAdyenWalletPaymentMethod) {
            /*
             * As the same `AvailabilityChecker` is used for all alternative payment methods,
             * we need to identify the payment method. This plugin overrides the `AvailabilityCheckerInterface`
             * and implements a custom method to check the availability based on the payment method code.
             */
            return $this->adyenAvailabilityChecker->isAvailableAdyenMethod($providerMethodCode);
        } else {
            return $proceed();
        }
    }
}

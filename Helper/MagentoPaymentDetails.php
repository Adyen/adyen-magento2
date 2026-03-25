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

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;

class MagentoPaymentDetails
{
    /**
     * @param Config $configHelper
     * @param CartRepositoryInterface $cartRepository
     * @param ConnectedTerminals $connectedTerminalsHelper
     * @param PaymentMethods $adyenPaymentMethodsHelper
     */
    public function __construct(
        protected readonly Config $configHelper,
        protected readonly CartRepositoryInterface $cartRepository,
        protected readonly ConnectedTerminals $connectedTerminalsHelper,
        protected readonly PaymentMethods $adyenPaymentMethodsHelper
    ) { }

    /**
     * @param PaymentDetailsInterface $result
     * @param int $cartId
     * @return PaymentDetailsInterface
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function addAdyenExtensionAttributes(PaymentDetailsInterface $result, int $cartId): PaymentDetailsInterface
    {
        $quote = $this->cartRepository->get($cartId);
        $storeId = $quote->getStoreId();
        $isAdyenPosCloudEnabled = $this->isAdyenPosCloudEnabled($result->getPaymentMethods(), $quote);

        if (!$this->configHelper->getIsPaymentMethodsActive($storeId) && !$isAdyenPosCloudEnabled) {
            return $result;
        }

        $extensionAttributes = $result->getExtensionAttributes();
        $extensionAttributes->setAdyenPaymentMethodsResponse($this->adyenPaymentMethodsHelper->getApiResponse($quote));

        if ($isAdyenPosCloudEnabled) {
            $connectedTerminals = $this->connectedTerminalsHelper->getConnectedTerminals($storeId);

            if (!empty($connectedTerminals['uniqueTerminalIds'])) {
                $extensionAttributes->setAdyenConnectedTerminals($connectedTerminals['uniqueTerminalIds']);
            }
        }

        $result->setExtensionAttributes($extensionAttributes);

        return $result;
    }

    /**
     * @param PaymentMethodInterface[] $paymentMethods
     */
    private function isAdyenPosCloudEnabled(array $paymentMethods, CartInterface $quote): bool
    {
        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->getCode() !== Config::XML_ADYEN_POS_CLOUD) {
                continue;
            }

            return !$paymentMethod instanceof MethodInterface || $paymentMethod->isAvailable($quote);
        }

        return false;
    }
}

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
use Magento\Quote\Api\CartRepositoryInterface;

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
        $isAdyenPosCloudEnabled = $this->configHelper->getAdyenPosCloudConfigData('active', $storeId, true);

        if (!$this->configHelper->getIsPaymentMethodsActive($storeId) && !$isAdyenPosCloudEnabled) {
            return $result;
        }

        $extensionAttributes = $result->getExtensionAttributes();
        $extensionAttributes->setAdyenPaymentMethodsResponse($this->adyenPaymentMethodsHelper->getApiResponse($quote));

        if ($isAdyenPosCloudEnabled) {
            $connectedTerminals = $this->connectedTerminalsHelper->getConnectedTerminalsApiResponse($storeId);

            if (!empty($connectedTerminals['uniqueTerminalIds'])) {
                $extensionAttributes->setAdyenConnectedTerminals($connectedTerminals['uniqueTerminalIds']);
            }
        }

        $result->setExtensionAttributes($extensionAttributes);

        return $result;
    }
}

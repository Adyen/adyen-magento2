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
    protected PaymentMethodsFilter $paymentMethodsFilter;
    protected Config $configHelper;
    protected CartRepositoryInterface $cartRepository;
    protected ConnectedTerminals $connectedTerminalsHelper;
    protected PaymentMethods $adyenPaymentMethodsHelper;

    public function __construct(
        PaymentMethodsFilter $paymentMethodsFilter,
        Config $configHelper,
        CartRepositoryInterface $cartRepository,
        ConnectedTerminals $connectedTerminals,
        PaymentMethods $adyenPaymentMethodsHelper
    ) {
        $this->paymentMethodsFilter = $paymentMethodsFilter;
        $this->configHelper = $configHelper;
        $this->cartRepository = $cartRepository;
        $this->connectedTerminalsHelper = $connectedTerminals;
        $this->adyenPaymentMethodsHelper = $adyenPaymentMethodsHelper;
    }

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
            $connectedTerminals = $this->connectedTerminalsHelper->getConnectedTerminals($storeId);

            if (!empty($connectedTerminals['uniqueTerminalIds'])) {
                $extensionAttributes->setAdyenConnectedTerminals($connectedTerminals['uniqueTerminalIds']);
            }
        }

        $result->setExtensionAttributes($extensionAttributes);

        return $result;
    }
}

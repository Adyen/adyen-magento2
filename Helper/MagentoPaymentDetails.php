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

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class MagentoPaymentDetails
{
    protected PaymentMethodsFilter $paymentMethodsFilter;
    protected Config $configHelper;
    protected CartRepositoryInterface $cartRepository;
    protected ConnectedTerminals $connectedTerminalsHelper;

    public function __construct(
        PaymentMethodsFilter $paymentMethodsFilter,
        Config $configHelper,
        CartRepositoryInterface $cartRepository,
        ConnectedTerminals $connectedTerminals
    ) {
        $this->paymentMethodsFilter = $paymentMethodsFilter;
        $this->configHelper = $configHelper;
        $this->cartRepository = $cartRepository;
        $this->connectedTerminalsHelper = $connectedTerminals;
    }

    public function addAdyenExtensionAttributes(PaymentDetailsInterface $result, int $cartId): PaymentDetailsInterface
    {
        $quote = $this->cartRepository->get($cartId);
        $storeId = $quote->getStoreId();
        $isAdyenPosCloudEnabled = $this->configHelper->getAdyenPosCloudConfigData('active', $storeId, true);

        if (!$this->configHelper->getIsPaymentMethodsActive($storeId) && !$isAdyenPosCloudEnabled) {
            return $result;
        }
        $magentoPaymentMethods = $result->getPaymentMethods();

        list($magentoPaymentMethods, $adyenPaymentMethodsResponse) =
            $this->paymentMethodsFilter->sortAndFilterPaymentMethods($magentoPaymentMethods, $quote);

        $result->setPaymentMethods($magentoPaymentMethods);
        $extensionAttributes = $result->getExtensionAttributes();

        $extensionAttributes->setAdyenPaymentMethodsResponse($adyenPaymentMethodsResponse);

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

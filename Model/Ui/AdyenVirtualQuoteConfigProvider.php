<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ConnectedTerminals;
use Adyen\Payment\Helper\PaymentMethods;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * This config provider is initially used to provide payment methods and connected terminals to the checkout
 * as the required `shipping-information` or `payment-information` API calls are not triggered for virtual quotes.
 */
class AdyenVirtualQuoteConfigProvider implements ConfigProviderInterface
{
    /**
     * @param PaymentMethods $paymentMethodsHelper
     * @param StoreManagerInterface $storeManager
     * @param Config $configHelper
     * @param Session $session
     * @param ConnectedTerminals $connectedTerminalsHelper
     */
    public function __construct(
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $configHelper,
        private readonly Session $session,
        private readonly ConnectedTerminals $connectedTerminalsHelper
    ) {}

    /**
     * @return array
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConfig(): array
    {
        $storeId = $this->storeManager->getStore()->getId();
        $config = [];

        if ($this->session->getQuote()->isVirtual()) {
            if ($this->configHelper->getIsPaymentMethodsActive($storeId)) {
                $config['payment']['adyen']['virtualQuote']['paymentMethodsResponse'] =
                    $this->paymentMethodsHelper->getApiResponse($this->session->getQuote());
            }

            if ($this->configHelper->getAdyenPosCloudConfigData("active", $storeId, true)) {
                $connectedTerminals = $this->connectedTerminalsHelper->getConnectedTerminals($storeId);
                if (!empty($connectedTerminals['uniqueTerminalIds'])) {
                    $config['payment']['adyen']['virtualQuote']['connectedTerminals'] =
                        $connectedTerminals['uniqueTerminalIds'];
                }
            }
        }

        return $config;
    }
}

<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\Config\Source\RenderMode;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;

class AdyenGenericConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_abstract';

    /**
     * @param Data $adyenHelper
     * @param Config $adyenConfigHelper
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param UrlInterface $url
     * @param AgreementsConfigProvider $agreementsConfigProvider
     * @param CspNonceProvider $cspNonceProvider
     * This data member will be passed to the js frontend. It will be used to map the method code (adyen_ideal) to the
     * corresponding txVariant (ideal). The txVariant will then be used to instantiate the component
     * @param array $txVariants
     * These payment methods have a custom method render file. This array has been used in the adyen-method.js
     * file to push correct payment method renderer.
     * @param array $customMethodRenderers
     */
    public function __construct(
        protected readonly Data $adyenHelper,
        private readonly Config $adyenConfigHelper,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly RequestInterface $request,
        protected readonly UrlInterface $url,
        private readonly AgreementsConfigProvider $agreementsConfigProvider,
        private readonly CspNonceProvider $cspNonceProvider,
        protected array $txVariants = [],
        protected array $customMethodRenderers = []
    ) { }

    public function getConfig(): array
    {
        $environment = $this->adyenConfigHelper->isDemoMode() ? 'test' : 'live';
        $storeId = $this->storeManager->getStore()->getId();
        $config = [
            'payment' => []
        ];
        // show logos turned on by default
        if ($this->showLogos()) {
            $config['payment']['adyen']['showLogo'] = true;
        } else {
            $config['payment']['adyen']['showLogo'] = false;
        }

        $config['payment']['adyen']['clientKey'] = $this->adyenConfigHelper->getClientKey($environment);
        $config['payment']['adyen']['cspNonce'] = $this->cspNonceProvider->generateNonce();
        $config['payment']['adyen']['merchantAccount'] = $this->adyenConfigHelper->getMerchantAccount($storeId);
        $config['payment']['adyen']['checkoutEnvironment'] = $this->adyenHelper->getCheckoutEnvironment($storeId);
        $config['payment']['adyen']['locale'] = $this->adyenHelper->getStoreLocale($storeId);
        $config['payment']['adyen']['chargedCurrency'] = $this->adyenConfigHelper->getChargedCurrency($storeId);
        $config['payment']['adyen']['hasHolderName'] = $this->adyenConfigHelper->getHasHolderName($storeId);
        $config['payment']['adyen']['holderNameRequired'] = $this->adyenConfigHelper->getHolderNameRequired($storeId);
        $config['payment']['adyen']['houseNumberStreetLine'] = $this->adyenConfigHelper
            ->getHouseNumberStreetLine($storeId);
        $config['payment']['customerStreetLinesEnabled'] = $this->adyenHelper->getCustomerStreetLinesEnabled($storeId);
        /* TODO: Do some filtering to only pass the payment methods that are enabled */
        $config['payment']['adyen']['txVariants'] = $this->txVariants;
        $config['payment']['adyen']['customMethodRenderers'] = $this->customMethodRenderers;
        $config['payment']['adyen']['successPage'] = $this->url->getUrl(
            'checkout/onepage/success',
            ['_secure' => $this->request->isSecure()]
        );
        $config['payment']['adyen']['agreementsConfig'] = $this->agreementsConfigProvider->getConfig();

        return $config;
    }

    protected function showLogos(): bool
    {
        $showLogos = $this->adyenConfigHelper->getAdyenAbstractConfigData('title_renderer');
        if ($showLogos == RenderMode::MODE_TITLE_IMAGE) {
            return true;
        }
        return false;
    }
}

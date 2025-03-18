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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;

class AdyenGenericConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_abstract';

    protected Data $adyenHelper;
    protected StoreManagerInterface $storeManager;
    protected RequestInterface $request;
    protected UrlInterface $url;
    private Config $adyenConfigHelper;
    private AgreementsConfigProvider $agreementsConfigProvider;
    /**
     * This data member will be passed to the js frontend. It will be used to map the method code (adyen_ideal) to the
     * corresponding txVariant (ideal). The txVariant will then be used to instantiate the component
     */
    protected array $txVariants;
    /**
     * These payment methods have a custom method render file. This array has been used in the adyen-method.js
     * file to push correct payment method renderer.
     */
    protected array $customMethodRenderers;

    public function __construct(
        Data $adyenHelper,
        Config $adyenConfigHelper,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        UrlInterface $url,
        AgreementsConfigProvider $agreementsConfigProvider,
        array $txVariants = [],
        array $customMethodRenderers = []
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenConfigHelper = $adyenConfigHelper;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->url = $url;
        $this->agreementsConfigProvider = $agreementsConfigProvider;
        $this->txVariants = $txVariants;
        $this->customMethodRenderers = $customMethodRenderers;
    }

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
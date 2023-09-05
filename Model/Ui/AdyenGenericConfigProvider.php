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

class AdyenGenericConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_abstract';

    // Seperate payment method codes
    const ADYEN_IDEAL_CODE = 'adyen_ideal';
    const ADYEN_AMAZONPAY_CODE = 'adyen_amazonpay';
    const ADYEN_APPLEPAY_CODE = 'adyen_applepay';
    const ADYEN_BCMC_MOBILE_CODE = 'adyen_bcmc_mobile';
    const ADYEN_DOTPAY_CODE = 'adyen_dotpay';
    const ADYEN_FACILYPAY_3X_CODE = 'adyen_facilypay_3x';
    const ADYEN_MULTIBANCO_CODE = 'adyen_multibanco';
    const ADYEN_GOOGLEPAY_CODE = 'adyen_googlepay';
    const ADYEN_KLARNA_CODE = 'adyen_klarna';
    const ADYEN_PAYPAL_CODE = 'adyen_paypal';
    const ADYEN_SEPADIRECTDEBIT_CODE = 'adyen_sepadirectdebit';

    // Vault payment method codes
    const ADYEN_SEPADIRECTDEBIT_VAULT_CODE = 'adyen_sepadirectdebit_vault';
    const ADYEN_KLARNA_VAULT_CODE = 'adyen_klarna_vault';
    const ADYEN_PAYPAL_VAULT_CODE = 'adyen_paypal_vault';
    const ADYEN_GOOGLEPAY_VAULT_CODE = 'adyen_googlepay_vault';
    const ADYEN_APPLEPAY_VAULT_CODE = 'adyen_applepay_vault';

    // Separate payment method tx_variants
    const IDEAL_TX_VARIANT = 'ideal';
    const AMAZONPAY_TX_VARIANT = 'amazonpay';
    const APPLEPAY_TX_VARIANT = 'applepay';
    const BCMC_MOBILE_TX_VARIANT = 'bcmc_mobile';
    const DOTPAY_TX_VARIANT = 'dotpay';
    const FACILYPAY_3X_TX_VARIANT = 'facilypay_3x';
    const MULTIBANCO_TX_VARIANT = 'multibanco';
    const GOOGLEPAY_TX_VARIANT = 'googlepay';
    const KLARNA_TX_VARIANT = 'klarna';
    const PAYPAL_TX_VARIANT = 'paypal';
    const SEPADIRECTDEBIT_TX_VARIANT = 'sepadirectdebit';

    protected Data $adyenHelper;
    protected StoreManagerInterface $storeManager;
    protected RequestInterface $request;
    protected UrlInterface $url;
    private Config $adyenConfigHelper;

    /**
     * This data member will be passed to the js frontend. It will be used to map the method code (adyen_ideal) to the
     * corresponding txVariant (ideal). The txVariant will then be used to instantiate the component
     *
     * @var array
     */
    private $txVariants;

    public function __construct(
        Data $adyenHelper,
        Config $adyenConfigHelper,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        UrlInterface $url,
        array $txVariants = []
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenConfigHelper = $adyenConfigHelper;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->url = $url;
        $this->txVariants = $txVariants;
    }

    /**
     * Define foreach payment methods the RedirectUrl
     *
     * @return array
     */
    public function getConfig()
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
        $config['payment']['adyen']['successPage'] = $this->url->getUrl(
            'checkout/onepage/success',
            ['_secure' => $this->request->isSecure()]
        );

        return $config;
    }

    /**
     * @return bool
     */
    protected function showLogos()
    {
        $showLogos = $this->adyenConfigHelper->getAdyenAbstractConfigData('title_renderer');
        if ($showLogos == RenderMode::MODE_TITLE_IMAGE) {
            return true;
        }
        return false;
    }
}

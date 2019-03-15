<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class PaymentMethods extends AbstractHelper
{
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface $config
     */
    protected $config;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\View\Asset\Source
     */
    protected $assetSource;

    /**
     * @var \Magento\Framework\View\DesignInterface
     */
    protected $design;

    /**
     * @var \Magento\Framework\View\Design\Theme\ThemeProviderInterface
     */
    protected $themeProvider;

    /**
     * PaymentMethods constructor.
     *
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param Data $adyenHelper
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\View\Asset\Source $assetSource
     * @param \Magento\Framework\View\DesignInterface $design
     * @param \Magento\Framework\View\Design\Theme\ThemeProviderInterface $themeProvider
     */
    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\View\Asset\Source $assetSource,
        \Magento\Framework\View\DesignInterface $design,
        \Magento\Framework\View\Design\Theme\ThemeProviderInterface $themeProvider
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->config = $config;
        $this->adyenHelper = $adyenHelper;
        $this->session = $session;
        $this->localeResolver = $localeResolver;
        $this->adyenLogger = $adyenLogger;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->assetSource = $assetSource;
        $this->design = $design;
        $this->themeProvider = $themeProvider;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentMethods($quoteId, $country = null)
    {
        // get quote from quoteId
        $quote = $this->quoteRepository->getActive($quoteId);
        $store = $quote->getStore();

        $paymentMethods = $this->addHppMethodsToConfig($store, $country);
        return $paymentMethods;
    }

    /**
     * @param $store
     * @return array
     */
    protected function addHppMethodsToConfig($store, $country)
    {
        $paymentMethods = [];

        $ccEnabled = $this->config->getValue(
            'payment/' . \Adyen\Payment\Model\Ui\AdyenCcConfigProvider::CODE . '/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $store->getCode()
        );
        $ccTypes = array_keys($this->adyenHelper->getCcTypesAltData());

        foreach ($this->fetchAlternativeMethods($store, $country) as $methodCode => $methodData) {
            /*
             * skip payment methods if it is a creditcard that is enabled in adyen_cc or a boleto method or wechat but
             * not wechatpay
             */
            if (($ccEnabled && in_array($methodCode, $ccTypes)) ||
                $this->adyenHelper->isPaymentMethodBoletoMethod($methodCode) ||
                $this->adyenHelper->isPaymentMethodBcmcMobileQRMethod($methodCode) ||
                $this->adyenHelper->isPaymentMethodWechatpayExceptWeb($methodCode)
            ) {
                continue;
            }

            $paymentMethods[$methodCode] = $methodData;
        }

        return $paymentMethods;
    }

    /**
     * @param $store
     * @param $country
     * @return array
     */
    protected function fetchAlternativeMethods($store, $country)
    {
        $merchantAccount = $this->adyenHelper->getAdyenAbstractConfigData('merchant_account');

        if (!$merchantAccount) {
            return [];
        }

        $adyFields = [
            "merchantAccount" => $merchantAccount,
            "countryCode" => $this->getCurrentCountryCode($store, $country),
            "amount" => [
                "currency" => $this->getCurrentCurrencyCode($store),
                "value" => (int)$this->adyenHelper->formatAmount(
                    $this->getCurrentPaymentAmount(),
                    $this->getCurrentCurrencyCode($store)
                ),
            ],
            "shopperReference" => $this->getCurrentShopperReference(),
            "shopperLocale" => $this->adyenHelper->getCurrentLocaleCode($store)
        ];

        $billingAddress = $this->getQuote()->getBillingAddress();

        if (!empty($billingAddress)) {
            if ($customerTelephone = trim($billingAddress->getTelephone())) {
                $adyFields['telephoneNumber'] = $customerTelephone;
            }
        }

        $responseData = $this->getPaymentMethodsResponse($adyFields, $store);

        $paymentMethods = [];
        if (isset($responseData['paymentMethods'])) {
            foreach ($responseData['paymentMethods'] as $paymentMethod) {

                if ($paymentMethod['type'] == "scheme") {
                    continue;
                }

                $paymentMethodCode = $paymentMethod['type'];
                $paymentMethod = $this->fieldMapPaymentMethod($paymentMethod);

                // check if payment method is an openinvoice method
                $paymentMethod['isPaymentMethodOpenInvoiceMethod'] =
                    $this->adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethodCode);

                // add icon location in result
                if ($this->adyenHelper->showLogos()) {
                    // Fix for MAGETWO-70402 https://github.com/magento/magento2/pull/7686
                    // Explicitly setting theme
                    $themeCode = "Magento/blank";

                    $themeId = $this->design->getConfigurationDesignTheme(\Magento\Framework\App\Area::AREA_FRONTEND);
                    if (!empty($themeId)) {
                        $theme = $this->themeProvider->getThemeById($themeId);
                        if ($theme && !empty($theme->getCode())) {
                            $themeCode = $theme->getCode();
                        }
                    }

                    $params = [];
                    $params = array_merge([
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                        '_secure' => $this->request->isSecure(),
                        'theme' => $themeCode
                    ], $params);

                    $asset = $this->assetRepo->createAsset('Adyen_Payment::images/logos/' .
                        $paymentMethodCode . '.png', $params);

                    $placeholder = $this->assetSource->findSource($asset);

                    $icon = null;
                    if ($placeholder) {
                        list($width, $height) = getimagesize($asset->getSourceFile());
                        $icon = [
                            'url' => $asset->getUrl(),
                            'width' => $width,
                            'height' => $height
                        ];
                    }
                    $paymentMethod['icon'] = $icon;
                }
                $paymentMethods[$paymentMethodCode] = $paymentMethod;
            }
        }

        return $paymentMethods;
    }

    /**
     * @return bool|int
     */
    protected function getCurrentPaymentAmount()
    {
        if (($grandTotal = $this->getQuote()->getGrandTotal()) > 0) {
            return $grandTotal;
        }
        return 10;
    }


    /**
     * @param $store
     * @return mixed
     */
    protected function getCurrentCurrencyCode($store)
    {
        return $this->getQuote()->getQuoteCurrencyCode() ?: $store->getBaseCurrencyCode();
    }

    /**
     * @param $store
     * @return int|mixed|string
     */
    protected function getCurrentCountryCode($store, $country)
    {
        // if fixed countryCode is setup in config use this
        $countryCode = $this->adyenHelper->getAdyenHppConfigData('country_code', $store->getId());

        if ($countryCode != "") {
            return $countryCode;
        }

        if ($country != null) {
            return $country;
        }

        if ($country = $this->getQuote()->getBillingAddress()->getCountry()) {
            return $country;
        }

        $defaultCountry = $this->config->getValue(
            \Magento\Tax\Model\Config::CONFIG_XML_PATH_DEFAULT_COUNTRY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $store->getCode()
        );

        if ($defaultCountry) {
            return $defaultCountry;
        }

        return "";
    }

    /**
     * @var array
     */
    protected $fieldMapPaymentMethod = [
        'name' => 'title'
    ];

    /**
     * @param $paymentMethod
     * @return mixed
     */
    protected function fieldMapPaymentMethod($paymentMethod)
    {
        foreach ($this->fieldMapPaymentMethod as $field => $newField) {
            if (isset($paymentMethod[$field])) {
                $paymentMethod[$newField] = $paymentMethod[$field];
                unset($paymentMethod[$field]);
            }
        }
        return $paymentMethod;
    }

    /**
     * @param $requestParams
     * @param $store
     * @return array
     * @throws \Adyen\AdyenException
     */
    protected function getPaymentMethodsResponse($requestParams, $store)
    {

        // initialize the adyen client
        $client = $this->adyenHelper->initializeAdyenClient($this->getQuote()->getStoreId());

        // initialize service
        $service = $this->adyenHelper->createAdyenCheckoutService($client);

        try {
            $responseData = $service->paymentMethods($requestParams);
        } catch (\Adyen\AdyenException $e) {
            $this->adyenLogger->error(
                "The Payment methods response is empty check your Adyen configuration in Magento."
            );
            // return empty result
            return [];
        }

        return $responseData;
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        return $this->session->getQuote();
    }

    /**
     * @return int
     */
    protected function getCurrentShopperReference()
    {
        return $this->getQuote()->getCustomerId();
    }
}
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
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote;

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

        // If quote cannot be found early return the empty paymentMethods array
        if (empty($quote)) {
            return [];
        }

        $this->setQuote($quote);

        $paymentMethods = $this->fetchAlternativeMethods($country);
        return $paymentMethods;
    }

    /**
     * @param $country
     * @return array
     */
    protected function fetchAlternativeMethods($country)
    {
        $quote = $this->getQuote();
        $store = $quote->getStore();

        $merchantAccount = $this->adyenHelper->getAdyenAbstractConfigData('merchant_account', $store->getId());

        if (!$merchantAccount) {
            return [];
        }

        $currencyCode = $this->getCurrentCurrencyCode($store);

        $adyFields = [
            "channel" => "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $this->getCurrentCountryCode($store, $country),
            "amount" => [
                "currency" => $currencyCode,
                "value" => $this->adyenHelper->formatAmount(
                    $this->getCurrentPaymentAmount(),
                    $currencyCode
                ),
            ],
            "shopperReference" => $this->getCurrentShopperReference(),
            "shopperLocale" => $this->adyenHelper->getCurrentLocaleCode($store->getId())
        ];

        $billingAddress = $quote->getBillingAddress();

        if (!empty($billingAddress)) {
            if ($customerTelephone = trim($billingAddress->getTelephone())) {
                $adyFields['telephoneNumber'] = $customerTelephone;
            }
        }

        $responseData = $this->getPaymentMethodsResponse($adyFields, $store);

        $paymentMethods = [];
        if (isset($responseData['paymentMethods'])) {
            foreach ($responseData['paymentMethods'] as $paymentMethod) {
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
                    $params = array_merge(
                        [
                            'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                            '_secure' => $this->request->isSecure(),
                            'theme' => $themeCode
                        ],
                        $params
                    );

                    $asset = $this->assetRepo->createAsset(
                        'Adyen_Payment::images/logos/' .
                        $paymentMethodCode . '.png',
                        $params
                    );

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
     * @return float
     */
    protected function getCurrentPaymentAmount()
    {
        $grandTotal = $this->getQuote()->getGrandTotal();

        if (!is_numeric($grandTotal)) {
            throw new \Exception(
                sprintf(
                    'Cannot retrieve a valid grand total from quote ID: `%s`. Expected a numeric value.',
                    $this->getQuote()->getEntityId()
                )
            );
        }

        $grandTotal = (float) $grandTotal;

        if ($grandTotal > 0.0) {
            return $grandTotal;
        }

        throw new \Exception(
            sprintf(
                'Cannot retrieve a valid grand total from quote ID: `%s`. Expected a float > `0.0`, got `%f`.',
                $this->getQuote()->getEntityId(),
                $grandTotal
            )
        );
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
        $client = $this->adyenHelper->initializeAdyenClient($store->getId());

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
        return $this->quote;
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     */
    protected function setQuote(\Magento\Quote\Model\Quote $quote)
    {
        $this->quote = $quote;
    }

    /**
     * @return int
     */
    protected function getCurrentShopperReference()
    {
        return $this->getQuote()->getCustomerId();
    }

    /**
     * @return array|mixed
     * @throws \Adyen\AdyenException
     */
    public function getConnectedTerminals()
    {
        $storeId = $this->getQuote()->getStoreId();

        // initialize the adyen client
        $client = $this->adyenHelper->initializeAdyenClient($storeId, $this->adyenHelper->getPosApiKey($storeId));

        // initialize service
        $service = $this->adyenHelper->createAdyenPosPaymentService($client);

        $requestParams = [
            "merchantAccount" => $this->adyenHelper->getAdyenMerchantAccount('adyen_pos_cloud', $storeId),
        ];

        // In case the POS store id is set, provide in the request
        if (!empty($this->adyenHelper->getPosStoreId($storeId))) {
            $requestParams['store'] = $this->adyenHelper->getPosStoreId($storeId);
        }

        try {
            $responseData = $service->getConnectedTerminals($requestParams);
        } catch (\Adyen\AdyenException $e) {
            $this->adyenLogger->error(
                "The getConnectedTerminals response is empty check your Adyen configuration in Magento."
            );
            // return empty result
            return [];
        }

        return $responseData;
    }
}

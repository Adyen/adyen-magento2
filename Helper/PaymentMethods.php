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

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class PaymentMethods extends AbstractHelper
{
    const ADYEN_HPP = 'adyen_hpp';
    const ADYEN_CC = 'adyen_cc';
    const ADYEN_ONE_CLICK = 'adyen_oneclick';

    const ADYEN_PREFIX = 'adyen_';

    const METHODS_WITH_BRAND_LOGO = [
        "giftcard"
    ];

    const METHODS_WITH_LOGO_FILE_MAPPING = [
        "scheme" => "card"
    ];

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
     * @var \Magento\Payment\Helper\Data
     */
    private $dataHelper;

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
     * @var ChargedCurrency
     */
    private $chargedCurrency;


    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param Data $adyenHelper
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\View\Asset\Source $assetSource
     * @param \Magento\Framework\View\DesignInterface $design
     * @param \Magento\Framework\View\Design\Theme\ThemeProviderInterface $themeProvider
     * @param ChargedCurrency $chargedCurrency
     * @param \Magento\Payment\Helper\Data $dataHelper
     */
    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\View\Asset\Source $assetSource,
        \Magento\Framework\View\DesignInterface $design,
        \Magento\Framework\View\Design\Theme\ThemeProviderInterface $themeProvider,
        ChargedCurrency $chargedCurrency,
        \Magento\Payment\Helper\Data $dataHelper
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->config = $config;
        $this->adyenHelper = $adyenHelper;
        $this->localeResolver = $localeResolver;
        $this->adyenLogger = $adyenLogger;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->assetSource = $assetSource;
        $this->design = $design;
        $this->themeProvider = $themeProvider;
        $this->chargedCurrency = $chargedCurrency;
        $this->dataHelper = $dataHelper;
    }

    /**
     * @param $quoteId
     * @param null $country
     * @return string|array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AdyenException
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

        return $this->fetchPaymentMethods($country);
    }

    /**
     * @param string $methodCode
     * @return bool
     */
    public function isAdyenPayment(string $methodCode): bool
    {
        return in_array($methodCode, $this->getAdyenPaymentMethods(), true);
    }

    /**
     * Returns an array of Adyen payment method codes
     *
     * @return string[]
     */
    public function getAdyenPaymentMethods() : array
    {
        $paymentMethods = $this->dataHelper->getPaymentMethodList();

        $filtered = array_filter(
            $paymentMethods,
            function ($key) {
                return strpos($key, self::ADYEN_PREFIX) === 0;
            },
            ARRAY_FILTER_USE_KEY
        );

        return array_keys($filtered);
    }

    /**
     * @param $country
     * @return string
     * @throws AdyenException
     * @throws LocalizedException
     * @throws \Exception
     */
    protected function fetchPaymentMethods($country): string
    {
        $quote = $this->getQuote();
        $store = $quote->getStore();

        $merchantAccount = $this->adyenHelper->getAdyenAbstractConfigData('merchant_account', $store->getId());
        if (!$merchantAccount) {
            return json_encode([]);
        }

        $paymentMethodRequest = $this->getPaymentMethodsRequest($merchantAccount, $store, $country, $quote);
        $responseData = $this->getPaymentMethodsResponse($paymentMethodRequest, $store);
        if (empty($responseData['paymentMethods'])) {
            return json_encode([]);
        }

        $paymentMethods = $responseData['paymentMethods'];
        $response['paymentMethodsResponse'] = $responseData;

        // Add extra details per payment method
        $paymentMethodsExtraDetails = [];
        $paymentMethodsExtraDetails = $this->showLogosPaymentMethods($paymentMethods, $paymentMethodsExtraDetails);
        $paymentMethodsExtraDetails = $this->addExtraConfigurationToPaymentMethods(
            $paymentMethods,
            $paymentMethodsExtraDetails
        );
        $response['paymentMethodsExtraDetails'] = $paymentMethodsExtraDetails;

        //TODO this should be the implemented with an interface
        return json_encode($response);
    }

    /**
     * @return float
     * @throws \Exception
     */
    protected function getCurrentPaymentAmount()
    {
        $total = $this->chargedCurrency->getQuoteAmountCurrency($this->getQuote())->getAmount();

        if (!is_numeric($total)) {
            throw new \Exception(
                sprintf(
                    'Cannot retrieve a valid grand total from quote ID: `%s`. Expected a numeric value.',
                    $this->getQuote()->getEntityId()
                )
            );
        }

        $total = (float)$total;

        if ($total >= 0) {
            return $total;
        }

        throw new \Exception(
            sprintf(
                'Cannot retrieve a valid grand total from quote ID: `%s`. Expected a float >= `0`, got `%f`.',
                $this->getQuote()->getEntityId(),
                $total
            )
        );
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
     * @param $requestParams
     * @param $store
     * @return array
     * @throws AdyenException
     */
    protected function getPaymentMethodsResponse($requestParams, $store)
    {
        // initialize the adyen client
        $client = $this->adyenHelper->initializeAdyenClient($store->getId());

        // initialize service
        $service = $this->adyenHelper->createAdyenCheckoutService($client);

        try {
            $responseData = $service->paymentMethods($requestParams);
        } catch (AdyenException $e) {
            $this->adyenLogger->error(
                "The Payment methods response is empty check your Adyen configuration in Magento."
            );
            // return empty result
            return [];
        }
        catch (\Adyen\ConnectionException $e) {
            $this->adyenLogger->error(
                "Connection to the endpoint failed. Check the Adyen Live endpoint prefix configuration."
            );
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
     * @param $merchantAccount
     * @param \Magento\Store\Model\Store $store
     * @param $country
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     * @throws \Exception
     */
    protected function getPaymentMethodsRequest(
        $merchantAccount,
        \Magento\Store\Model\Store $store,
        $country,
        \Magento\Quote\Model\Quote $quote
    ) {
        $currencyCode = $this->chargedCurrency->getQuoteAmountCurrency($quote)->getCurrencyCode();

        $paymentMethodRequest = [
            "channel" => "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $this->getCurrentCountryCode($store, $country),
            "shopperLocale" => $this->adyenHelper->getCurrentLocaleCode($store->getId()),
            "amount" => [
                "currency" => $currencyCode
            ]
        ];

        if (!empty($this->getCurrentShopperReference())) {
            $paymentMethodRequest["shopperReference"] = str_pad(
                $this->getCurrentShopperReference(),
                3,
                '0',
                STR_PAD_LEFT
            );
        }

        $amountValue = $this->adyenHelper->formatAmount($this->getCurrentPaymentAmount(), $currencyCode);

        if (!empty($amountValue)) {
            $paymentMethodRequest["amount"]["value"] = $amountValue;
        }

        $billingAddress = $quote->getBillingAddress();

        if (!empty($billingAddress) && !is_null($billingAddress->getTelephone())) {
            $paymentMethodRequest['telephoneNumber'] = trim($billingAddress->getTelephone());
        }

        return $paymentMethodRequest;
    }

    /**
     * @param $paymentMethods
     * @param array $paymentMethodsExtraDetails
     * @return array
     * @throws LocalizedException
     */
    protected function showLogosPaymentMethods($paymentMethods, array $paymentMethodsExtraDetails)
    {
        if (!$this->adyenHelper->showLogos()) {
            return $paymentMethodsExtraDetails;
        }
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

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodCode = in_array($paymentMethod['type'], self::METHODS_WITH_BRAND_LOGO)
                ? $paymentMethod['brand']
                : $paymentMethod['type'];

            $paymentMethodCode = !empty(self::METHODS_WITH_LOGO_FILE_MAPPING[$paymentMethod['type']])
                ? self::METHODS_WITH_LOGO_FILE_MAPPING[$paymentMethod['type']]
                : $paymentMethodCode;

            $asset = $this->assetRepo->createAsset(
                'Adyen_Payment::images/logos/' .
                $paymentMethodCode . '.png',
                $params
            );

            $placeholder = $this->assetSource->findSource($asset);

            if ($placeholder) {
                list($width, $height) = getimagesize($asset->getSourceFile());
                $icon = [
                    'url' => $asset->getUrl(),
                    'width' => $width,
                    'height' => $height
                ];
            } else {
                $icon = [
                    'url' => 'https://checkoutshopper-live.adyen.com/checkoutshopper/images/logos/medium/' . $paymentMethodCode . '.png',
                    'width' => 77,
                    'height' => 50
                ];
            }

            $paymentMethodsExtraDetails[$paymentMethodCode]['icon'] = $icon;

            //todo check if it is needed
            // check if payment method is an open invoice method
            $paymentMethodsExtraDetails[$paymentMethodCode]['isOpenInvoice'] =
                $this->adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethodCode);
        }
        return $paymentMethodsExtraDetails;
    }

    protected function addExtraConfigurationToPaymentMethods($paymentMethods, array $paymentMethodsExtraDetails)
    {
        $quote = $this->getQuote();
        $currencyCode = $this->chargedCurrency->getQuoteAmountCurrency($quote)->getCurrencyCode();
        $amountValue = $this->adyenHelper->formatAmount($this->getCurrentPaymentAmount(), $currencyCode);

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodCode = $paymentMethod['type'];

            $paymentMethodsExtraDetails[$paymentMethodCode]['configuration'] = [
                'amount' => [
                    'value' => $amountValue,
                    'currency' => $currencyCode
                ],
                'currency' => $currencyCode,
            ];
        }

        return $paymentMethodsExtraDetails;
    }

    /**
     * Checks if a payment is wallet payment method
     * @param $notificationPaymentMethod
     * @return bool
     */
    public function isWalletPaymentMethod($notificationPaymentMethod): bool
    {
        $walletPaymentMethods = [
            'googlepay',
            'paywithgoogle',
            'wechatpayWeb',
            'amazonpay',
            'applepay',
            'wechatpayQR',
            'alipay',
            'alipay_hk'
        ];
        return in_array($notificationPaymentMethod, $walletPaymentMethods);
    }

    /**
     * Check if the method of the passed payment is equal to the method passed in this function
     *
     * @param $payment
     * @param string $method
     * @return bool
     */
    public function checkPaymentMethod($payment, string $method): bool
    {
        return $payment->getMethod() === $method;
    }

    /**
     * Check if the passed payment method supports recurring functionality.
     *
     * Currently only SEPA is allowed on our Magento plugin.
     * Possible future payment methods:
     *
     * 'ach','amazonpay','applepay','directdebit_GB','bcmc','dana','dankort','eps','gcash','giropay','googlepay','paywithgoogle',
     * 'gopay_wallet','ideal','kakaopay','klarna','klarna_account','klarna_b2b','klarna_paynow','momo_wallet','paymaya_wallet',
     * 'paypal','trustly','twint','uatp','billdesk_upi','payu_IN_upi','vipps','yandex_money','zip'
     *
     * @param string $paymentMethod
     * @return bool
     */
    public function paymentMethodSupportsRecurring(string $paymentMethod): bool
    {
        $paymentMethodRecurring = [
            'sepadirectdebit',
        ];

        return in_array($paymentMethod, $paymentMethodRecurring);
    }
}

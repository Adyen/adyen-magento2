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
    protected $_quoteRepository;

    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $_quoteIdMaskFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface $config
     */
    protected $_config;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var Repository
     */
    protected $_assetRepo;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Framework\View\Asset\Source
     */
    protected $_assetSource;

    /**
     * PaymentMethods constructor.
     *
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param Data $adyenHelper
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\View\Asset\Source $assetSource
     */
    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\View\Asset\Source $assetSource
    ) {
        $this->_quoteRepository = $quoteRepository;
        $this->_quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->_config = $config;
        $this->_adyenHelper = $adyenHelper;
        $this->_session = $session;
        $this->_localeResolver = $localeResolver;
        $this->_adyenLogger = $adyenLogger;
        $this->_assetRepo = $assetRepo;
        $this->_request = $request;
        $this->_assetSource = $assetSource;

    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentMethods($quoteId, $country = null)
    {
        // get quote from quoteId
        $quote = $this->_quoteRepository->getActive($quoteId);
        $store = $quote->getStore();
        $paymentMethods = $this->_addHppMethodsToConfig($store, $country);
        return $paymentMethods;
    }

    /**
     * @param $store
     * @return array
     */
    protected function _addHppMethodsToConfig($store, $country)
    {
        $paymentMethods = [];

        $ccEnabled = $this->_config->getValue('payment/'.\Adyen\Payment\Model\Ui\AdyenCcConfigProvider::CODE.'/active');
        $ccTypes = array_keys($this->_adyenHelper->getCcTypesAltData());
        $sepaEnabled = $this->_config->getValue(
            'payment/'.\Adyen\Payment\Model\Ui\AdyenSepaConfigProvider::CODE.'/active'
        );

        foreach ($this->_fetchHppMethods($store, $country) as $methodCode => $methodData) {
            /*
             * skip payment methods if it is a creditcard that is enabled in adyen_cc
             * or if payment is sepadirectdebit and SEPA api is enabled
             */
            if ($ccEnabled && in_array($methodCode, $ccTypes)) {
                continue;
            } elseif ($methodCode == 'sepadirectdebit' && $sepaEnabled) {
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
    protected function _fetchHppMethods($store, $country)
    {
        $skinCode = $this->_adyenHelper->getAdyenHppConfigData('skin_code');
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData('merchant_account');

        if (!$skinCode || !$merchantAccount) {
            return [];
        }

        $adyFields = [
            "paymentAmount"     => (int) $this->_adyenHelper->formatAmount(
                $this->_getCurrentPaymentAmount(),
                $this->_getCurrentCurrencyCode($store)
            ),
            "currencyCode"      => $this->_getCurrentCurrencyCode($store),
            "merchantReference" => "Get Payment methods",
            "skinCode"          => $skinCode,
            "merchantAccount"   => $merchantAccount,
            "sessionValidity"   => date(
                DATE_ATOM,
                mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y"))
            ),
            "countryCode"       => $this->_getCurrentCountryCode($store, $country),
            "shopperLocale"     => $this->_getCurrentLocaleCode($store)
        ];

        $responseData = $this->_getDirectoryLookupResponse($adyFields, $store);

        $paymentMethods = [];
        if (isset($responseData['paymentMethods'])) {
            foreach ($responseData['paymentMethods'] as $paymentMethod) {
                $paymentMethodCode = $paymentMethod['brandCode'];
                $paymentMethod = $this->_fieldMapPaymentMethod($paymentMethod);


                // check if payment method is an openinvoice method
                $paymentMethod['isPaymentMethodOpenInvoiceMethod'] =
                    $this->_adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethodCode);

                // add icon location in result
                if ($this->_adyenHelper->showLogos()) {


                    $params = [];
                    // use frontend area
                    $params = array_merge(['area' => 'frontend', '_secure' => $this->_request->isSecure()], $params);

                    $asset = $this->_assetRepo->createAsset('Adyen_Payment::images/logos/' .
                        $paymentMethodCode . '.png', $params);

                    $placeholder = $this->_assetSource->findSource($asset);

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
    protected function _getCurrentPaymentAmount()
    {
        if (($grandTotal = $this->_getQuote()->getGrandTotal()) > 0) {
            return $grandTotal;
        }
        return 10;
    }


    /**
     * @param $store
     * @return mixed
     */
    protected function _getCurrentCurrencyCode($store)
    {
        return $this->_getQuote()->getQuoteCurrencyCode() ?: $store->getBaseCurrencyCode();
    }

    /**
     * @param $store
     * @return int|mixed|string
     */
    protected function _getCurrentCountryCode($store, $country)
    {
        // if fixed countryCode is setup in config use this
        $countryCode = $this->_adyenHelper->getAdyenHppConfigData('country_code', $store->getId());

        if ($countryCode != "") {
            return $countryCode;
        }

        if ($country != null) {
            return $country;
        }

        if ($country = $this->_getQuote()->getBillingAddress()->getCountry()) {
            return $country;
        }

        $defaultCountry = $this->_config->getValue(
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
     * @param $store
     * @return mixed|string
     */
    protected function _getCurrentLocaleCode($store)
    {
        $localeCode = $this->_adyenHelper->getAdyenAbstractConfigData('shopper_locale', $store->getId());
        if ($localeCode != "") {
            return $localeCode;
        }

        $locale = $this->_localeResolver->getLocale();
        if ($locale) {
            return $locale;
        }

        // should have the vulue if not fall back to default
        $localeCode = $this->_config->getValue(
            Data::XML_PATH_DEFAULT_LOCALE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $store->getCode()
        );

        return $localeCode;
    }

    /**
     * @var array
     */
    protected $_fieldMapPaymentMethod = [
        'name' => 'title'
    ];

    /**
     * @param $paymentMethod
     * @return mixed
     */
    protected function _fieldMapPaymentMethod($paymentMethod)
    {
        foreach ($this->_fieldMapPaymentMethod as $field => $newField) {
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
    protected function _getDirectoryLookupResponse($requestParams, $store)
    {
        $cacheKey = $this->_getCacheKeyForRequest($requestParams, $store);

        // initialize the adyen client
        $client = new \Adyen\Client();

        if ($this->_adyenHelper->isDemoMode()) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            $client->setEnvironment(\Adyen\Environment::LIVE);
        }

        // connect to magento log
        $client->setLogger($this->_adyenLogger);

        $hmacKey = $this->_adyenHelper->getHmac();

        // create and add signature
        try {
            $requestParams["merchantSig"] = \Adyen\Util\Util::calculateSha256Signature($hmacKey, $requestParams);
        } catch (\Adyen\AdyenException $e) {
            $this->_adyenLogger->error($e->getMessage());
            // return empty result
            return [];
        }

        // initialize service
        $service = new \Adyen\Service\DirectoryLookup($client);

        try {
            $responseData =  $service->directoryLookup($requestParams);
        } catch (\Adyen\AdyenException $e) {
            $this->_adyenLogger->error(
                "The Directory Lookup response is empty check your Adyen configuration in Magento."
            );
            // return empty result
            return [];
        }

        return $responseData;
    }

    /**
     * @var array
     */
    protected $_cacheParams = array(
        'currencyCode',
        'merchantReference',
        'skinCode',
        'merchantAccount',
        'countryCode',
        'shopperLocale',
    );

    /**
     * @param $requestParams
     * @param $store
     * @return string
     */
    protected function _getCacheKeyForRequest($requestParams, $store)
    {
        $cacheParams = [];
        $cacheParams['store'] = $store->getId();
        foreach ($this->_cacheParams as $paramKey) {
            if (isset($requestParams[$paramKey])) {
                $cacheParams[$paramKey] = $requestParams[$paramKey];
            }
        }

        return md5(implode('|', $cacheParams));
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    protected function _getQuote()
    {
        return $this->_session->getQuote();
    }
}
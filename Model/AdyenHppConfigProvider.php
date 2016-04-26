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

namespace Adyen\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Directory\Helper\Data;

class AdyenHppConfigProvider implements ConfigProviderInterface
{

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var AdyenGenericConfig
     */
    protected $_genericConfig;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface $config
     */
    protected $_config;

    /**
     * @var string[]
     */
    protected $methodCodes = [
        'adyen_hpp'
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param PaymentHelper $paymentHelper
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param AdyenGenericConfig $genericConfig
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        PaymentHelper $paymentHelper,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\AdyenGenericConfig $genericConfig
    ) {
        $this->_appState = $context->getAppState();
        $this->_session = $session;
        $this->_storeManager = $storeManager;
        $this->_paymentHelper = $paymentHelper;
        $this->_localeResolver = $localeResolver;
        $this->_config = $config;
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->_genericConfig = $genericConfig;

        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $this->_paymentHelper->getMethodInstance($code);
        }
    }


    public function getConfig()
    {
        $config = [
            'payment' => [
                'adyenHpp' => [
                ]
            ]
        ];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                // get payment methods
                $config['payment'] ['adyenHpp']['paymentMethods'] = $this->getAdyenHppPaymentMethods();
            }
        }

        $paymentMethodSelectionOnAdyen = $this->_adyenHelper->getAdyenHppConfigDataFlag('payment_selection_on_adyen');
        $config['payment'] ['adyenHpp']['isPaymentMethodSelectionOnAdyen'] = $paymentMethodSelectionOnAdyen;

        return $config;
    }


    public function getAdyenHppPaymentMethods()
    {
        $paymentMethods = null;

        // is admin?
        if ($this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            //retrieve storeId from quote
            $store = $this->_session->getQuote()->getStore();
        } else {
            $store = $this->_storeManager->getStore();
        }

        // is adyen HPP enabled ?
        $hppActive = $this->methods['adyen_hpp']->isAvailable();

        if($hppActive) {
            $paymentMethods = $this->_addHppMethodsToConfig($store);
        }

        return $paymentMethods;
    }

    protected function _addHppMethodsToConfig($store)
    {
        $paymentMethods = [];

        $ccEnabled = $this->_config->getValue('payment/'.\Adyen\Payment\Model\Method\Cc::METHOD_CODE.'/active');
        $ccTypes = array_keys($this->_adyenHelper->getCcTypesAltData());
        $sepaEnabled = $this->_config->getValue('payment/'.\Adyen\Payment\Model\Method\Sepa::METHOD_CODE.'/active');

        foreach ($this->_fetchHppMethods($store) as $methodCode => $methodData) {

            // skip payment methods if it is a creditcard that is enabled in adyen_cc or if payment is sepadirectdebit and SEPA api is enabled
            if ($ccEnabled && in_array($methodCode, $ccTypes)) {
                continue;
            } elseif($methodCode == 'sepadirectdebit' && $sepaEnabled) {
                continue;
            }

            $paymentMethods[$methodCode] = $methodData;
        }

        return $paymentMethods;
    }

    protected function _fetchHppMethods($store)
    {
        $skinCode = $this->_adyenHelper->getAdyenHppConfigData('skin_code');
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData('merchant_account');

        if (!$skinCode || !$merchantAccount) {
            return array();
        }

        $adyFields = array(
            "paymentAmount"     => (int) $this->_adyenHelper->formatAmount($this->_getCurrentPaymentAmount(), $this->_getCurrentCurrencyCode($store)),
            "currencyCode"      => $this->_getCurrentCurrencyCode($store),
            "merchantReference" => "Get Payment methods",
            "skinCode"          => $skinCode,
            "merchantAccount"   => $merchantAccount,
            "sessionValidity"   => date(
                DATE_ATOM,
                mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y"))
            ),
            "countryCode"       => $this->_getCurrentCountryCode($store),
            "shopperLocale"     => $this->_getCurrentLocaleCode($store)
        );

        $responseData = $this->_getDirectoryLookupResponse($adyFields, $store);

        $paymentMethods = array();
        if(isset($responseData['paymentMethods'])) {
            foreach ($responseData['paymentMethods'] as $paymentMethod) {

                $paymentMethodCode = $paymentMethod['brandCode'];
                $paymentMethod = $this->_fieldMapPaymentMethod($paymentMethod);

                // add icon location in result
                if($this->_genericConfig->showLogos()) {
                    $asset = $this->_genericConfig->createAsset('Adyen_Payment::images/logos/' . $paymentMethodCode . '.png');

                    $placeholder = $this->_genericConfig->findRelativeSourceFilePath($asset);

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
     * @return string
     */
    protected function _getCurrentCurrencyCode($store)
    {
        return $this->_getQuote()->getQuoteCurrencyCode() ?: $store->getBaseCurrencyCode();
    }

    /**
     * @return string
     */
    protected function _getCurrentCountryCode($store)
    {

        // if fixed countryCode is setup in config use this
        $countryCode = $this->_adyenHelper->getAdyenHppConfigData('country_code', $store->getId());

        if($countryCode != "") {
            return $countryCode;
        }

        if ($country = $this->_getQuote()->getBillingAddress()->getCountry()) {
            return $country;
        }

        $defaultCountry = $this->_config->getValue(
            \Magento\Tax\Model\Config::CONFIG_XML_PATH_DEFAULT_COUNTRY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $store->getCode()
        );

        if($defaultCountry) {
            return $defaultCountry;
        }

        return "";
    }

    /**
     * @return string
     */
    protected function _getCurrentLocaleCode($store)
    {
        $localeCode = $this->_adyenHelper->getAdyenAbstractConfigData('shopper_locale', $store->getId());
        if($localeCode != "") {
            return $localeCode;
        }

        $locale = $this->_localeResolver->getLocale();
        if($locale) {
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

    protected $_fieldMapPaymentMethod = array(
        'name' => 'title'
    );

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

    protected function _getDirectoryLookupResponse($requestParams, $store)
    {
        $cacheKey = $this->_getCacheKeyForRequest($requestParams, $store);

        // initialize the adyen client
        $client = new \Adyen\Client();

        if($this->_adyenHelper->isDemoMode()) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            $client->setEnvironment(\Adyen\Environment::LIVE);
        }

        // connect to magento log
        $client->setLogger($this->_adyenLogger);

        $hmacKey = $this->_adyenHelper->getHmac();

        // create and add signature
        $requestParams["merchantSig"] = \Adyen\Util\Util::calculateSha256Signature($hmacKey, $requestParams);

        // initialize service
        $service = new \Adyen\Service\DirectoryLookup($client);

        try {
            $responseData =  $service->directoryLookup($requestParams);
        }catch (Exception $e) {
            $this->_adyenLogger->error("The Directory Lookup response is empty check your Adyen configuration in Magento.");
            // return empty result
            return array();
        }

        // save result in cache
//        Mage::app()->getCache()->save(
//            serialize($responseData),
//            $cacheKey,
//            array(Mage_Core_Model_Config::CACHE_TAG),
//            60 * 60 * 6
//        );

        return $responseData;
    }

    protected $_cacheParams = array(
        'currencyCode',
        'merchantReference',
        'skinCode',
        'merchantAccount',
        'countryCode',
        'shopperLocale',
    );

    protected function _getCacheKeyForRequest($requestParams, $store)
    {
        $cacheParams = array();
        $cacheParams['store'] = $store->getId();
        foreach ($this->_cacheParams as $paramKey) {
            if (isset($requestParams[$paramKey])) {
                $cacheParams[$paramKey] = $requestParams[$paramKey];
            }
        }

        return md5(implode('|', $cacheParams));
    }

    protected function _getQuote()
    {
        return $this->_session->getQuote();
    }


    /**
     * Create a file asset that's subject of fallback system
     *
     * @param string $fileId
     * @param array $params
     * @return \Magento\Framework\View\Asset\File
     */
    protected function _createAsset($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->request->isSecure()], $params);
        return $this->assetRepo->createAsset($fileId, $params);
    }

}
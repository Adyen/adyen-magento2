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
use Magento\Framework\App\Cache\Type\Config as ConfigCache;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Data extends AbstractHelper
{
    const MODULE_NAME = 'adyen-magento2';
    const TEST = 'test';
    const LIVE = 'live';
    // Only used for backend orders! Checkout in front-end is using different checkout version see web folder
    const CHECKOUT_COMPONENT_JS_LIVE = 'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/3.2.0/adyen.js';
    const CHECKOUT_COMPONENT_JS_TEST = 'https://checkoutshopper-test.adyen.com/checkoutshopper/sdk/3.2.0/adyen.js';

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Magento\Framework\Config\DataInterface
     */
    protected $_dataStorage;

    /**
     * @var \Magento\Directory\Model\Config\Source\Country
     */
    protected $_country;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var \Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory
     */
    protected $_billingAgreementCollectionFactory;

    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    protected $_assetRepo;

    /**
     * @var \Magento\Framework\View\Asset\Source
     */
    protected $_assetSource;

    /**
     * @var \Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory
     */
    protected $_notificationFactory;

    /**
     * @var \Magento\Tax\Model\Config
     */
    protected $_taxConfig;

    /**
     * @var \Magento\Tax\Model\Calculation
     */
    protected $_taxCalculation;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var \Adyen\Payment\Model\Billing\AgreementFactory
     */
    protected $billingAgreementFactory;

    /**
     * @var ResourceModel\Billing\Agreement
     */
    private $agreementResourceModel;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $localeResolver;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $config;

    /**
     * @var \Magento\Backend\Helper\Data $helperBackend
     */
    private $helperBackend;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Framework\Component\ComponentRegistrarInterface
     */
    private $componentRegistrar;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Framework\Config\DataInterface $dataStorage
     * @param \Magento\Directory\Model\Config\Source\Country $country
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory $billingAgreementCollectionFactory
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\View\Asset\Source $assetSource
     * @param \Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory $notificationFactory
     * @param \Magento\Tax\Model\Config $taxConfig
     * @param \Magento\Tax\Model\Calculation $taxCalculation
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Adyen\Payment\Model\Billing\AgreementFactory $billingAgreementFactory
     * @param \Adyen\Payment\Model\ResourceModel\Billing\Agreement $agreementResourceModel
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Backend\Helper\Data $helperBackend
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Config\DataInterface $dataStorage,
        \Magento\Directory\Model\Config\Source\Country $country,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory $billingAgreementCollectionFactory,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\View\Asset\Source $assetSource,
        \Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory $notificationFactory,
        \Magento\Tax\Model\Config $taxConfig,
        \Magento\Tax\Model\Calculation $taxCalculation,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\CacheInterface $cache,
        \Adyen\Payment\Model\Billing\AgreementFactory $billingAgreementFactory,
        \Adyen\Payment\Model\ResourceModel\Billing\Agreement $agreementResourceModel,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Backend\Helper\Data $helperBackend,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\Component\ComponentRegistrarInterface $componentRegistrar
    ) {
        parent::__construct($context);
        $this->_encryptor = $encryptor;
        $this->_dataStorage = $dataStorage;
        $this->_country = $country;
        $this->_moduleList = $moduleList;
        $this->_billingAgreementCollectionFactory = $billingAgreementCollectionFactory;
        $this->_assetRepo = $assetRepo;
        $this->_assetSource = $assetSource;
        $this->_notificationFactory = $notificationFactory;
        $this->_taxConfig = $taxConfig;
        $this->_taxCalculation = $taxCalculation;
        $this->productMetadata = $productMetadata;
        $this->adyenLogger = $adyenLogger;
        $this->storeManager = $storeManager;
        $this->cache = $cache;
        $this->billingAgreementFactory = $billingAgreementFactory;
        $this->agreementResourceModel = $agreementResourceModel;
        $this->localeResolver = $localeResolver;
        $this->config = $config;
        $this->helperBackend = $helperBackend;
        $this->serializer = $serializer;
        $this->componentRegistrar = $componentRegistrar;
    }

    /**
     * return recurring types for configuration setting
     *
     * @return array
     */
    public function getRecurringTypes()
    {
        return [
            \Adyen\Payment\Model\RecurringType::ONECLICK => 'ONECLICK',
            \Adyen\Payment\Model\RecurringType::ONECLICK_RECURRING => 'ONECLICK,RECURRING',
            \Adyen\Payment\Model\RecurringType::RECURRING => 'RECURRING'
        ];
    }

    /**
     * return recurring types for configuration setting
     *
     * @return array
     */
    public function getModes()
    {
        return [
            '1' => 'Test Mode',
            '0' => 'Production Mode'
        ];
    }

    /**
     * return recurring types for configuration setting
     *
     * @return array
     */
    public function getCaptureModes()
    {
        return [
            'auto' => 'immediate',
            'manual' => 'manual'
        ];
    }

    /**
     * return recurring types for configuration setting
     *
     * @return array
     */
    public function getPaymentRoutines()
    {
        return [
            'single' => 'Single Page Payment Routine',
            'multi' => 'Multi-page Payment Routine'
        ];
    }

    /**
     * Return the number of decimals for the specified currency
     *
     * @param $currency
     * @return int
     */
    public function decimalNumbers($currency)
    {
        switch ($currency) {
            case "CVE":
            case "DJF":
            case "GNF":
            case "IDR":
            case "JPY":
            case "KMF":
            case "KRW":
            case "PYG":
            case "RWF":
            case "UGX":
            case "VND":
            case "VUV":
            case "XAF":
            case "XOF":
            case "XPF":
                $format = 0;
                break;
            case "BHD":
            case "IQD":
            case "JOD":
            case "KWD":
            case "LYD":
            case "OMR":
            case "TND":
                $format = 3;
                break;
            default:
                $format = 2;
        }
        return $format;
    }

    /**
     * Return the formatted amount. Adyen accepts the currency in multiple formats.
     *
     * @param $amount
     * @param $currency
     * @return int
     */
    public function formatAmount($amount, $currency)
    {
        return (int)number_format($amount, $this->decimalNumbers($currency), '', '');
    }

    /**
     * Tax Percentage needs to be in minor units for Adyen
     *
     * @param float $taxPercent
     * @return int
     */
    public function getMinorUnitTaxPercent($taxPercent)
    {
        $taxPercent = $taxPercent * 100;
        return (int)$taxPercent;
    }

    /**
     * @param $amount
     * @param $currency
     * @return float
     */
    public function originalAmount($amount, $currency)
    {
        // check the format
        switch ($currency) {
            case "JPY":
            case "IDR":
            case "KRW":
            case "BYR":
            case "VND":
            case "CVE":
            case "DJF":
            case "GNF":
            case "PYG":
            case "RWF":
            case "UGX":
            case "VUV":
            case "XAF":
            case "XOF":
            case "XPF":
            case "GHC":
            case "KMF":
                $format = 1;
                break;
            case "MRO":
                $format = 10;
                break;
            case "BHD":
            case "JOD":
            case "KWD":
            case "OMR":
            case "LYD":
            case "TND":
                $format = 1000;
                break;
            default:
                $format = 100;
                break;
        }

        return ($amount / $format);
    }

    /**
     * gives back global configuration values
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenAbstractConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_abstract', $storeId);
    }

    /**
     * gives back global configuration values as boolean
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenAbstractConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_abstract', $storeId, true);
    }

    /**
     * Gives back adyen_cc configuration values
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenCcConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_cc', $storeId);
    }

    /**
     * Gives back adyen_cc configuration values as flag
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenCcConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_cc', $storeId, true);
    }

    /**
     * Gives back adyen_cc_vault configuration values as flag
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenCcVaultConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_cc_vault', $storeId, true);
    }

    /**
     * Gives back adyen_hpp configuration values
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenHppConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_hpp', $storeId);
    }

    /**
     * Gives back adyen_hpp configuration values as flag
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenHppConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_hpp', $storeId, true);
    }

    /**
     * Gives back adyen_hpp_vault configuration values as flag
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenHppVaultConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_hpp_vault', $storeId, true);
    }

    /**
     * Gives back adyen_oneclick configuration values
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenOneclickConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_oneclick', $storeId);
    }

    /**
     * Gives back adyen_oneclick configuration values as flag
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenOneclickConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_oneclick', $storeId, true);
    }

    /**
     * @param $field
     * @param null|int|string $storeId
     * @return bool|mixed
     */
    public function getAdyenPosCloudConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_pos_cloud', $storeId);
    }

    /**
     * @param $field
     * @param null|int|string $storeId
     * @return bool|mixed
     */
    public function getAdyenPosCloudConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_pos_cloud', $storeId, true);
    }

    /**
     * Gives back adyen_pay_by_mail configuration values
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenPayByMailConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_pay_by_mail', $storeId);
    }

    /**
     * Gives back adyen_pay_by_mail configuration values as flag
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenPayByMailConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_pay_by_mail', $storeId, true);
    }

    /**
     * Gives back adyen_boleto configuration values
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenBoletoConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_boleto', $storeId);
    }

    /**
     * Gives back adyen_boleto configuration values as flag
     *
     * @param $field
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdyenBoletoConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_boleto', $storeId, true);
    }

    /**
     * Retrieve decrypted hmac key
     *
     * @return string
     */
    public function getHmac()
    {
        switch ($this->isDemoMode()) {
            case true:
                $secretWord = $this->_encryptor->decrypt(trim($this->getAdyenHppConfigData('hmac_test')));
                break;
            default:
                $secretWord = $this->_encryptor->decrypt(trim($this->getAdyenHppConfigData('hmac_live')));
                break;
        }
        return $secretWord;
    }

    public function getHmacPayByMail()
    {
        switch ($this->isDemoMode()) {
            case true:
                $secretWord = $this->_encryptor->decrypt(trim($this->getAdyenPayByMailConfigData('hmac_test')));
                break;
            default:
                $secretWord = $this->_encryptor->decrypt(trim($this->getAdyenPayByMailConfigData('hmac_live')));
                break;
        }
        return $secretWord;
    }

    /**
     * Check if configuration is set to demo mode
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function isDemoMode($storeId = null)
    {
        return $this->getAdyenAbstractConfigDataFlag('demo_mode', $storeId);
    }

    /**
     * Retrieve the decrypted notification password
     *
     * @return string
     */
    public function getNotificationPassword()
    {
        return $this->_encryptor->decrypt(trim($this->getAdyenAbstractConfigData('notification_password')));
    }

    /**
     * Retrieve the API key
     *
     * @param null|int|string $storeId
     * @return string
     */
    public function getAPIKey($storeId = null)
    {
        if ($this->isDemoMode($storeId)) {
            $apiKey = $this->_encryptor->decrypt(
                trim(
                    $this->getAdyenAbstractConfigData(
                        'api_key_test',
                        $storeId
                    )
                )
            );
        } else {
            $apiKey = $this->_encryptor->decrypt(
                trim(
                    $this->getAdyenAbstractConfigData(
                        'api_key_live',
                        $storeId
                    )
                )
            );
        }
        return $apiKey;
    }

    /**
     * Retrieve the Client key
     *
     * @param null|int|string $storeId
     * @return string
     */
    public function getClientKey($storeId = null)
    {
        return trim(
            $this->getAdyenAbstractConfigData(
                $this->isDemoMode($storeId) ? 'client_key_test' : 'client_key_live',
                $storeId
            )
        );
    }

    /**
     * Retrieve the webserver username
     *
     * @param null|int|string $storeId
     * @return string
     */
    public function getWsUsername($storeId = null)
    {
        if ($this->isDemoMode($storeId)) {
            $wsUsername = trim($this->getAdyenAbstractConfigData('ws_username_test', $storeId));
        } else {
            $wsUsername = trim($this->getAdyenAbstractConfigData('ws_username_live', $storeId));
        }
        return $wsUsername;
    }

    /**
     * Retrieve the Live endpoint prefix key
     *
     * @param null|int|string $storeId
     * @return string
     */
    public function getLiveEndpointPrefix($storeId = null)
    {
        $prefix = trim($this->getAdyenAbstractConfigData('live_endpoint_url_prefix', $storeId));
        return $prefix;
    }

    /**
     * Cancels the order
     *
     * @param $order
     */
    public function cancelOrder($order)
    {
        $orderStatus = $this->getAdyenAbstractConfigData('payment_cancelled');
        $order->setActionFlag($orderStatus, true);

        switch ($orderStatus) {
            case \Magento\Sales\Model\Order::STATE_HOLDED:
                if ($order->canHold()) {
                    $order->hold()->save();
                }
                break;
            default:
                if ($order->canCancel()) {
                    $order->cancel()->save();
                }
                break;
        }
    }

    /**
     * Creditcard type that is selected is different from creditcard type that we get back from the request this
     * function get the magento creditcard type this is needed for getting settings like installments
     *
     * @param $ccType
     * @return mixed
     */
    public function getMagentoCreditCartType($ccType)
    {
        $ccTypesMapper = $this->getCcTypesAltData();

        if (isset($ccTypesMapper[$ccType])) {
            $ccType = $ccTypesMapper[$ccType]['code'];
        }

        return $ccType;
    }

    /**
     * @return array
     */
    public function getCcTypesAltData()
    {
        $adyenCcTypes = $this->getAdyenCcTypes();
        $types = [];
        foreach ($adyenCcTypes as $key => $data) {
            $types[$data['code_alt']] = $data;
            $types[$data['code_alt']]['code'] = $key;
        }
        return $types;
    }

    /**
     * @return mixed
     */
    public function getAdyenCcTypes()
    {
        return $this->_dataStorage->get('adyen_credit_cards');
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param $field
     * @param $paymentMethodCode
     * @param null|int|string $storeId
     * @param bool|false $flag
     * @return bool|mixed
     */
    public function getConfigData($field, $paymentMethodCode, $storeId = null, $flag = false)
    {
        $path = 'payment/' . $paymentMethodCode . '/' . $field;

        if (!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    /**
     * Get adyen magento module's name sent to Adyen
     *
     * @return string
     */
    public function getModuleName()
    {
        return (string)self::MODULE_NAME;
    }

    /**
     * Get adyen magento module's version from composer.json
     *
     * @return string
     */
    public function getModuleVersion()
    {
        $moduleDir = $this->componentRegistrar->getPath(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            'Adyen_Payment'
        );

        $composerJson = file_get_contents($moduleDir . '/composer.json');
        $composerJson = json_decode($composerJson, true);

        if (empty($composerJson['version'])) {
            return "Version is not available in composer.json";
        }

        return $composerJson['version'];
    }

    public function getBoletoTypes()
    {
        return [
            [
                'value' => 'boletobancario_itau',
                'label' => __('boletobancario_itau'),
            ],
            [
                'value' => 'boletobancario_santander',
                'label' => __('boletobancario_santander'),
            ],
            [
                'value' => 'primeiropay_boleto',
                'label' => __('primeiropay_boleto'),
            ]
        ];
    }

    /**
     * @param $customerId
     * @param $storeId
     * @param $grandTotal
     * @param $recurringType
     * @return array
     */
    public function getOneClickPaymentMethods($customerId, $storeId, $grandTotal)
    {
        $billingAgreements = [];

        $baCollection = $this->_billingAgreementCollectionFactory->create();
        $baCollection->addFieldToFilter('customer_id', $customerId);
        if ($this->isPerStoreBillingAgreement($storeId)) {
            $baCollection->addFieldToFilter('store_id', $storeId);
        }
        $baCollection->addFieldToFilter('method_code', 'adyen_oneclick');
        $baCollection->addActiveFilter();

        foreach ($baCollection as $billingAgreement) {
            $agreementData = $billingAgreement->getAgreementData();

            // no agreementData and contractType then ignore
            if ((!is_array($agreementData)) || (!isset($agreementData['contractTypes']))) {
                continue;
            }

            // check if contractType is supporting the selected contractType for OneClick payments
            $allowedContractTypes = $agreementData['contractTypes'];
            if (in_array(\Adyen\Payment\Model\RecurringType::ONECLICK , $allowedContractTypes)) {
                // check if AgreementLabel is set and if contract has an recurringType
                if ($billingAgreement->getAgreementLabel()) {
                    // for Ideal use sepadirectdebit because it is
                    if ($agreementData['variant'] == 'ideal') {
                        $agreementData['variant'] = 'sepadirectdebit';
                    }

                    $data = [
                        'reference_id' => $billingAgreement->getReferenceId(),
                        'agreement_label' => $billingAgreement->getAgreementLabel(),
                        'agreement_data' => $agreementData
                    ];

                    if ($this->showLogos()) {
                        $logoName = $agreementData['variant'];

                        $asset = $this->createAsset(
                            'Adyen_Payment::images/logos/' . $logoName . '.png'
                        );

                        $icon = null;
                        $placeholder = $this->_assetSource->findSource($asset);
                        if ($placeholder) {
                            list($width, $height) = getimagesize($asset->getSourceFile());
                            $icon = [
                                'url' => $asset->getUrl(),
                                'width' => $width,
                                'height' => $height
                            ];
                        }
                        $data['logo'] = $icon;
                    }

                    /**
                     * Check if there are installments for this creditcard type defined
                     */
                    $data['number_of_installments'] = 0;
                    $ccType = $this->getMagentoCreditCartType($agreementData['variant']);
                    $installments = null;
                    $installmentsValue = $this->getAdyenCcConfigData('installments');
                    if ($installmentsValue) {
                        $installments = $this->serializer->unserialize($installmentsValue);
                    }

                    if ($installments) {
                        $numberOfInstallments = [];

                        foreach ($installments as $ccTypeInstallment => $installment) {
                            if ($ccTypeInstallment == $ccType) {
                                foreach ($installment as $amount => $installments) {
                                    if ($grandTotal >= $amount) {
                                        array_push($numberOfInstallments, $installments);
                                    }
                                }
                            }
                        }
                        if ($numberOfInstallments) {
                            sort($numberOfInstallments);
                            $data['number_of_installments'] = $numberOfInstallments;
                        }
                    }
                    $billingAgreements[] = $data;
                }
            }
        }
        return $billingAgreements;
    }

    public function isPerStoreBillingAgreement($storeId)
    {
        return !$this->getAdyenOneclickConfigDataFlag('share_billing_agreement', $storeId);
    }

    /**
     * @param $paymentMethod
     * @return bool
     */
    public function isPaymentMethodOpenInvoiceMethod($paymentMethod)
    {
        if (strpos($paymentMethod, 'afterpay') !== false ||
            strpos($paymentMethod, 'klarna') !== false ||
            strpos($paymentMethod, 'ratepay') !== false ||
            strpos($paymentMethod, 'facilypay_') !== false ||
            strpos($paymentMethod, 'affirm') !== false ||
            strpos($paymentMethod, 'clearpay') !== false ||
            strpos($paymentMethod, 'zip') !== false
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param $paymentMethod
     * @return bool
     */
    public function isPaymentMethodRatepayMethod($paymentMethod)
    {
        if (strpos($paymentMethod, 'ratepay') !== false) {
            return true;
        }

        return false;
    }

    /**
     * @param $paymentMethod
     * @return bool
     */
    public function isPaymentMethodAfterpayTouchMethod($paymentMethod)
    {
        if (strpos($paymentMethod, 'afterpaytouch') !== false) {
            return true;
        }

        return false;
    }

    /**
     * @param $paymentMethod
     * @return bool
     */
    public function isPaymentMethodMolpayMethod($paymentMethod)
    {
        if (strpos($paymentMethod, 'molpay_') !== false) {
            return true;
        }

        return false;
    }

    /**
     * @param $paymentMethod
     * @return bool
     */
    public function isPaymentMethodOneyMethod($paymentMethod)
    {
        if (strpos($paymentMethod, 'facilypay_') !== false) {
            return true;
        }

        return false;
    }

    /**
     * @param $paymentMethod
     * @return bool
     */
    public function doesPaymentMethodSkipDetails($paymentMethod)
    {
        if ($this->isPaymentMethodOpenInvoiceMethod($paymentMethod) ||
            $this->isPaymentMethodMolpayMethod($paymentMethod) ||
            $this->isPaymentMethodOneyMethod($paymentMethod)
        ) {
            return true;
        }

        return false;
    }

    public function getRatePayId()
    {
        return $this->getAdyenHppConfigData("ratepay_id");
    }

    /**
     * For Klarna And AfterPay use VatCategory High others use none
     *
     * @param $paymentMethod
     * @return bool
     */
    public function isVatCategoryHigh($paymentMethod)
    {
        if ($paymentMethod == "klarna" ||
            strlen($paymentMethod) >= 9 && substr($paymentMethod, 0, 9) == 'afterpay_'
        ) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function showLogos()
    {
        $showLogos = $this->getAdyenAbstractConfigData('title_renderer');
        if ($showLogos == \Adyen\Payment\Model\Config\Source\RenderMode::MODE_TITLE_IMAGE) {
            return true;
        }
        return false;
    }

    /**
     * Create a file asset that's subject of fallback system
     *
     * @param string $fileId
     * @param array $params
     * @return \Magento\Framework\View\Asset\File
     */
    public function createAsset($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->_request->isSecure()], $params);
        return $this->_assetRepo->createAsset($fileId, $params);
    }

    public function getStoreLocale($storeId)
    {
        $path = \Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCustomerStreetLinesEnabled($storeId)
    {
        $path = 'customer/address/street_lines';
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Format Magento locale codes with undersocre to ISO locale codes with dash
     *
     * @param $localeCode
     */
    public function formatLocaleCode($localeCode)
    {
        return str_replace("_", "-", $localeCode);
    }

    public function getUnprocessedNotifications()
    {
        $notifications = $this->_notificationFactory->create();
        $notifications->unprocessedNotificationsFilter();
        return $notifications->getSize();
    }

    /**
     * @param $formFields
     * @param $count
     * @param $name
     * @param $price
     * @param $currency
     * @param $taxAmount
     * @param $priceInclTax
     * @param $taxPercent
     * @param $numberOfItems
     * @param $payment
     * @param null $itemId
     * @return mixed
     */
    public function createOpenInvoiceLineItem(
        $formFields,
        $count,
        $name,
        $price,
        $currency,
        $taxAmount,
        $priceInclTax,
        $taxPercent,
        $numberOfItems,
        $payment,
        $itemId = null
    ) {
        $description = str_replace("\n", '', trim($name));
        $itemAmount = $this->formatAmount($price, $currency);

        $itemVatAmount = $this->getItemVatAmount(
            $taxAmount,
            $priceInclTax,
            $price,
            $currency
        );

        // Calculate vat percentage
        $itemVatPercentage = $this->getMinorUnitTaxPercent($taxPercent);

        return $this->getOpenInvoiceLineData(
            $formFields,
            $count,
            $currency,
            $description,
            $itemAmount,
            $itemVatAmount,
            $itemVatPercentage,
            $numberOfItems,
            $payment,
            $itemId
        );
    }

    /**
     * @param $formFields
     * @param $count
     * @param $order
     * @param $shippingAmount
     * @param $shippingTaxAmount
     * @param $currency
     * @param $payment
     * @return mixed
     */
    public function createOpenInvoiceLineShipping(
        $formFields,
        $count,
        $order,
        $shippingAmount,
        $shippingTaxAmount,
        $currency,
        $payment
    ) {
        $description = $order->getShippingDescription();
        $itemAmount = $this->formatAmount($shippingAmount, $currency);
        $itemVatAmount = $this->formatAmount($shippingTaxAmount, $currency);

        // Create RateRequest to calculate the Tax class rate for the shipping method
        $rateRequest = $this->_taxCalculation->getRateRequest(
            $order->getShippingAddress(),
            $order->getBillingAddress(),
            null,
            $order->getStoreId(),
            $order->getCustomerId()
        );

        $taxClassId = $this->_taxConfig->getShippingTaxClass($order->getStoreId());
        $rateRequest->setProductClassId($taxClassId);
        $rate = $this->_taxCalculation->getRate($rateRequest);

        $itemVatPercentage = $this->getMinorUnitTaxPercent($rate);
        $numberOfItems = 1;

        return $this->getOpenInvoiceLineData(
            $formFields,
            $count,
            $currency,
            $description,
            $itemAmount,
            $itemVatAmount,
            $itemVatPercentage,
            $numberOfItems,
            $payment,
            "shippingCost"
        );
    }

    /**
     * @param $taxAmount
     * @param $priceInclTax
     * @param $price
     * @param $currency
     * @return string
     */
    public function getItemVatAmount(
        $taxAmount,
        $priceInclTax,
        $price,
        $currency
    ) {
        if ($taxAmount > 0 && $priceInclTax > 0) {
            return $this->formatAmount($priceInclTax, $currency) - $this->formatAmount($price, $currency);
        }
        return $this->formatAmount($taxAmount, $currency);
    }

    /**
     * Set the openinvoice line
     *
     * @param $formFields
     * @param $count
     * @param $currencyCode
     * @param $description
     * @param $itemAmount
     * @param $itemVatAmount
     * @param $itemVatPercentage
     * @param $numberOfItems
     * @param $payment
     * @param null|int $itemId optional
     * @return mixed
     */
    public function getOpenInvoiceLineData(
        $formFields,
        $count,
        $currencyCode,
        $description,
        $itemAmount,
        $itemVatAmount,
        $itemVatPercentage,
        $numberOfItems,
        $payment,
        $itemId = null
    ) {
        $linename = "line" . $count;

        // item id is optional
        if ($itemId) {
            $formFields['openinvoicedata.' . $linename . '.itemId'] = $itemId;
        }

        $formFields['openinvoicedata.' . $linename . '.currencyCode'] = $currencyCode;
        $formFields['openinvoicedata.' . $linename . '.description'] = $description;
        $formFields['openinvoicedata.' . $linename . '.itemAmount'] = $itemAmount;
        $formFields['openinvoicedata.' . $linename . '.itemVatAmount'] = $itemVatAmount;
        $formFields['openinvoicedata.' . $linename . '.itemVatPercentage'] = $itemVatPercentage;
        $formFields['openinvoicedata.' . $linename . '.numberOfItems'] = $numberOfItems;

        if ($this->isVatCategoryHigh(
            $payment->getAdditionalInformation(
                \Adyen\Payment\Observer\AdyenHppDataAssignObserver::BRAND_CODE
            )
        )
        ) {
            $formFields['openinvoicedata.' . $linename . '.vatCategory'] = "High";
        } else {
            $formFields['openinvoicedata.' . $linename . '.vatCategory'] = "None";
        }
        return $formFields;
    }

    /**
     * @param null|int|string $storeId
     * @return string the X API Key for the specified or current store
     */
    public function getPosApiKey($storeId = null)
    {
        if ($this->isDemoMode($storeId)) {
            $apiKey = $this->_encryptor->decrypt(trim($this->getAdyenPosCloudConfigData('api_key_test', $storeId)));
        } else {
            $apiKey = $this->_encryptor->decrypt(trim($this->getAdyenPosCloudConfigData('api_key_live', $storeId)));
        }
        return $apiKey;
    }

    /**
     * Return the Store ID for the current store/mode
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getPosStoreId($storeId = null)
    {
        return $this->getAdyenPosCloudConfigData('pos_store_id', $storeId);
    }

    /**
     * Return the merchant account name configured for the proper payment method.
     * If it is not configured for the specific payment method,
     * return the merchant account name defined in required settings.
     *
     * @param $paymentMethod
     * @param null|int|string $storeId
     * @return string
     */
    public function getAdyenMerchantAccount($paymentMethod, $storeId = null)
    {
        if (!$storeId) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $merchantAccount = $this->getAdyenAbstractConfigData("merchant_account", $storeId);
        $merchantAccountPos = $this->getAdyenPosCloudConfigData('pos_merchant_account', $storeId);

        if ($paymentMethod == 'adyen_pos_cloud' && !empty($merchantAccountPos)) {
            return $merchantAccountPos;
        }
        return $merchantAccount;
    }

    /**
     * Format the Receipt sent in the Terminal API response in HTML
     * so that it can be easily shown to the shopper
     *
     * @param $paymentReceipt
     * @return string
     */
    public function formatTerminalAPIReceipt($paymentReceipt)
    {
        $formattedHtml = "<table class='terminal-api-receipt'>";
        foreach ($paymentReceipt as $receipt) {
            if ($receipt['DocumentQualifier'] == "CustomerReceipt") {
                foreach ($receipt['OutputContent']['OutputText'] as $item) {
                    parse_str($item['Text'], $textParts);
                    $formattedHtml .= "<tr class='terminal-api-receipt'>";
                    if (!empty($textParts['name'])) {
                        $formattedHtml .= "<td class='terminal-api-receipt-name'>" . $textParts['name'] . "</td>";
                    } else {
                        $formattedHtml .= "<td class='terminal-api-receipt-name'>&nbsp;</td>";
                    }
                    if (!empty($textParts['value'])) {
                        $formattedHtml .= "<td class='terminal-api-receipt-value' align='right'>"
                            . $textParts['value'] . "</td>";
                    } else {
                        $formattedHtml .= "<td class='terminal-api-receipt-value' align='right'>&nbsp;</td>";
                    }
                    $formattedHtml .= "</tr>";
                }
            }
        }
        $formattedHtml .= "</table>";
        return $formattedHtml;
    }

    /**
     * Initializes and returns Adyen Client and sets the required parameters of it
     *
     * @param null|int|string $storeId
     * @param string|null $apiKey
     * @return \Adyen\Client
     * @throws \Adyen\AdyenException
     */
    public function initializeAdyenClient($storeId = null, $apiKey = null)
    {
        // initialize client
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        if (empty($apiKey)) {
            $apiKey = $this->getAPIKey($storeId);
        }

        $client = $this->createAdyenClient();
        $client->setApplicationName("Magento 2 plugin");
        $client->setXApiKey($apiKey);
        $moduleVersion = $this->getModuleVersion();

        $client->setAdyenPaymentSource($this->getModuleName(), $moduleVersion);
        $client->setExternalPlatform($this->productMetadata->getName(), $this->productMetadata->getVersion());
        if ($this->isDemoMode($storeId)) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            $client->setEnvironment(\Adyen\Environment::LIVE, $this->getLiveEndpointPrefix($storeId));
        }

        $client->setLogger($this->adyenLogger);

        return $client;
    }

    /**
     * @param \Adyen\Client $client
     * @return \Adyen\Service\PosPayment
     * @throws \Adyen\AdyenException
     */
    public function createAdyenPosPaymentService($client)
    {
        return new \Adyen\Service\PosPayment($client);
    }

    /**
     * @return \Adyen\Client
     * @throws \Adyen\AdyenException
     */
    private function createAdyenClient()
    {
        return new \Adyen\Client();
    }

    /**
     * @param null|int|string $storeId
     * @return string
     */
    public function getOrigin($storeId)
    {
        if ( $paymentOriginUrl = $this->getAdyenAbstractConfigData("payment_origin_url", $storeId) ) {
            return $paymentOriginUrl;
        }
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $state = $objectManager->get(\Magento\Framework\App\State::class);
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        if ('adminhtml' === $state->getAreaCode()) {
            $baseUrl = $this->helperBackend->getHomePageUrl();
        }
        $parsed = parse_url($baseUrl);
        $origin = $parsed['scheme'] . "://" . $parsed['host'];
        if (!empty($parsed['port'])) {
            $origin .= ":" . $parsed['port'];
        }
        return $origin;
    }

    /**
     * Retrieve origin keys for platform's base url
     *
     * @return string
     * @throws \Adyen\AdyenException
     * @deprecared please use getClientKey instead
     */
    public function getOriginKeyForBaseUrl()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $origin = $this->getOrigin($storeId);
        $cacheKey = 'Adyen_origin_key_for_' . $origin . '_' . $storeId;

        if (!$originKey = $this->cache->load($cacheKey)) {
            if ($originKey = $this->getOriginKeyForOrigin($origin, $storeId)) {
                $this->cache->save($originKey, $cacheKey, [ConfigCache::CACHE_TAG], 60 * 60 * 24);
            }
        }

        return $originKey;
    }

    /**
     * Get origin key for a specific origin using the adyen api library client
     *
     * @param $origin
     * @param null|int|string $storeId
     * @return string
     * @throws \Adyen\AdyenException
     */
    private function getOriginKeyForOrigin($origin, $storeId = null)
    {
        $params = [
            "originDomains" => [
                $origin
            ]
        ];

        $client = $this->initializeAdyenClient($storeId);

        try {
            $service = $this->createAdyenCheckoutUtilityService($client);
            $response = $service->originKeys($params);
        } catch (\Exception $e) {
            $this->adyenLogger->error($e->getMessage());
        }

        $originKey = "";

        if (!empty($response['originKeys'][$origin])) {
            $originKey = $response['originKeys'][$origin];
        }

        return $originKey;
    }

    /**
     * @param null|int|string $storeId
     * @return string
     */
    public function getCheckoutEnvironment($storeId = null)
    {
        if ($this->isDemoMode($storeId)) {
            return self::TEST;
        }

        return self::LIVE;
    }

    /**
     * @param \Adyen\Client $client
     * @return \Adyen\Service\CheckoutUtility
     * @throws \Adyen\AdyenException
     */
    private function createAdyenCheckoutUtilityService($client)
    {
        return new \Adyen\Service\CheckoutUtility($client);
    }

    /**
     * @param null|int|string $storeId
     * @return string
     */
    public function getCheckoutCardComponentJs($storeId = null)
    {
        if ($this->isDemoMode($storeId)) {
            return self::CHECKOUT_COMPONENT_JS_TEST;
        }

        return self::CHECKOUT_COMPONENT_JS_LIVE;
    }

    /**
     * @param $order
     * @param $additionalData
     */
    public function createAdyenBillingAgreement($order, $additionalData)
    {
        if (!empty($additionalData['recurring.recurringDetailReference'])) {
            $listRecurringContracts = null;
            try {
                // Get or create billing agreement
                /** @var \Adyen\Payment\Model\Billing\Agreement $billingAgreement */
                $billingAgreement = $this->billingAgreementFactory->create();
                $billingAgreement->load($additionalData['recurring.recurringDetailReference'], 'reference_id');

                // check if BA exists
                if (!($billingAgreement && $billingAgreement->getAgreementId() > 0 && $billingAgreement->isValid())) {
                    // create new BA
                    $billingAgreement = $this->billingAgreementFactory->create();
                    $billingAgreement->setStoreId($order->getStoreId());
                    $billingAgreement->importOrderPaymentWithRecurringDetailReference(
                        $order->getPayment(),
                        $additionalData['recurring.recurringDetailReference']
                    );

                    $message = __(
                        'Created billing agreement #%1.',
                        $additionalData['recurring.recurringDetailReference']
                    );
                } else {
                    $billingAgreement->setIsObjectChanged(true);
                    $message = __(
                        'Updated billing agreement #%1.',
                        $additionalData['recurring.recurringDetailReference']
                    );
                }

                // Populate billing agreement data
                $storeOneClick = $order->getPayment()->getAdditionalInformation('store_cc');

                $billingAgreement->setCcBillingAgreement($additionalData, $storeOneClick, $order->getStoreId());
                $billingAgreementErrors = $billingAgreement->getErrors();

                if ($billingAgreement->isValid() && empty($billingAgreementErrors)) {
                    if (!$this->agreementResourceModel->getOrderRelation(
                        $billingAgreement->getAgreementId(),
                        $order->getId()
                    )) {
                        // save into billing_agreement_order
                        $billingAgreement->addOrderRelation($order);
                    }
                    // add to order to save agreement
                    $order->addRelatedObject($billingAgreement);
                } else {
                    $message = __('Failed to create billing agreement for this order. Reason(s): ') . join(
                            ', ',
                            $billingAgreementErrors
                        );
                    throw new \Exception($message);
                }
            } catch (\Exception $exception) {
                $message = $exception->getMessage();
                $this->adyenLogger->error("exception: " . $message);
            }

            $comment = $order->addStatusHistoryComment($message);

            $order->addRelatedObject($comment);
            $order->save();
        }
    }

    /**
     * Method can be used by interceptors to provide the customer ID in a different way.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return int|null
     */
    public function getCustomerId(\Magento\Sales\Model\Order $order)
    {
        return $order->getCustomerId();
    }

    /**
     * For backwards compatibility get the recurringType used for HPP + current billing agreements
     *
     * @param null|int|string $storeId
     * @return null|string
     */
    public function getRecurringTypeFromOneclickRecurringSetting($storeId = null)
    {
        $enableOneclick = $this->getAdyenAbstractConfigDataFlag('enable_oneclick', $storeId);
        $adyenCCVaultActive = $this->getAdyenCcVaultConfigDataFlag('active', $storeId);

        if ($enableOneclick && $adyenCCVaultActive) {
            return \Adyen\Payment\Model\RecurringType::ONECLICK_RECURRING;
        } elseif ($enableOneclick && !$adyenCCVaultActive) {
            return \Adyen\Payment\Model\RecurringType::ONECLICK;
        } elseif (!$enableOneclick && $adyenCCVaultActive) {
            return \Adyen\Payment\Model\RecurringType::ONECLICK_RECURRING;
        } else {
            return \Adyen\Payment\Model\RecurringType::NONE;
        }
    }

    /**
     * Get icon from variant
     *
     * @param $variant
     * @return array
     */
    public function getVariantIcon($variant)
    {
        $asset = $this->createAsset(sprintf("Adyen_Payment::images/logos/%s_small.png", $variant));
        list($width, $height) = getimagesize($asset->getSourceFile());
        $icon = [
            'url' => $asset->getUrl(),
            'width' => $width,
            'height' => $height
        ];
        return $icon;
    }

    /**
     * Check if CreditCard vault is enabled
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function isCreditCardVaultEnabled($storeId = null)
    {
        return $this->getAdyenCcVaultConfigDataFlag('active', $storeId);
    }

    /**
     * Check if HPP vault is enabled
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function isHppVaultEnabled($storeId = null)
    {
        return $this->getAdyenHppVaultConfigDataFlag('active', $storeId);
    }

    /**
     * Check if 3DS2.0 is enabled for credit cards
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function isCreditCardThreeDS2Enabled($storeId = null)
    {
        return $this->getAdyenCcConfigDataFlag('threeds2_enabled', $storeId);
    }

    /**
     * @param $client
     * @return \Adyen\Service\Checkout
     */
    public function createAdyenCheckoutService($client)
    {
        return new \Adyen\Service\Checkout($client);
    }

    /**
     * @param $client
     * @return \Adyen\Service\Recurring
     * @throws \Adyen\AdyenException
     */
    public function createAdyenRecurringService($client)
    {
        return new \Adyen\Service\Recurring($client);
    }

    /**
     * @param string $date
     * @param string $format
     * @return mixed
     */
    public function formatDate($date = null, $format = 'Y-m-d H:i:s')
    {
        if (strlen($date) < 0) {
            $date = date('d-m-Y H:i:s');
        }
        $timeStamp = new \DateTime($date);
        return $timeStamp->format($format);
    }

    /**
     * @param string|null $type
     * @param string|null $token
     * @return string
     */
    public function buildThreeDS2ProcessResponseJson($type = null, $token = null)
    {
        $response = ['threeDS2' => false];

        if (!empty($type)) {
            $response['type'] = $type;
        }

        if ($type && $token) {
            $response['threeDS2'] = true;
            $response['token'] = $token;
        }

        return json_encode($response);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed|string
     */
    public function getCurrentLocaleCode($storeId)
    {
        $localeCode = $this->getAdyenHppConfigData('shopper_locale', $storeId);
        if ($localeCode != "") {
            return $localeCode;
        }

        $locale = $this->localeResolver->getLocale();
        if ($locale) {
            return $locale;
        }

        // should have the value if not fall back to default
        $localeCode = $this->config->getValue(
            \Magento\Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore($storeId)->getCode()
        );

        return $localeCode;
    }

    /**
     * Get the Customer Area PSP Search URL with a preset PSP Reference
     *
     * @param string $pspReference
     * @param string $liveEnvironment
     * @return string
     */
    public function getPspReferenceSearchUrl($pspReference, $liveEnvironment)
    {
        if ($liveEnvironment === "true") {
            $checkoutEnvironment = "live";
        } else {
            $checkoutEnvironment = "test";
        }
        return sprintf(
            "https://ca-%s.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=%s",
            $checkoutEnvironment,
            $pspReference
        );
    }
}

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
use Adyen\Client;
use Adyen\Environment;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\RecurringType;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory as NotificationCollectionFactory;
use Adyen\Service\Checkout\ModificationsApi;
use Adyen\Service\Checkout\OrdersApi;
use Adyen\Service\Checkout\PaymentLinksApi;
use Adyen\Service\Checkout\DonationsApi;
use Adyen\Service\Checkout\PaymentsApi;
use Adyen\Service\PosPayment;
use Adyen\Service\RecurringApi;
use Adyen\Payment\Helper\PlatformInfo;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Config\DataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Data extends AbstractHelper
{

    const APPLICATION_NAME = 'Magento 2 plugin';
    const TEST = 'test';
    const LIVE = 'live';
    const LIVE_AU = 'live-au';
    const LIVE_US = 'live-us';
    const LIVE_IN = 'live-in';

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var DataInterface
     */
    protected $_dataStorage;

    /**
     * @var Country
     */
    protected $_country;

    /**
     * @var ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var Repository
     */
    protected $_assetRepo;

    /**
     * @var Source
     */
    protected $_assetSource;

    /**
     * @var NotificationCollectionFactory
     */
    protected $_notificationFactory;

    /**
     * @var Config
     */
    protected $_taxConfig;

    /**
     * @var Calculation
     */
    protected $_taxCalculation;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var PlatformInfo
     */
    private PlatformInfo $platformInfo;

    /**
     * Request object
     *
     * @var RequestInterface
     */
    protected $_request;

    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        DataInterface $dataStorage,
        Country $country,
        ModuleListInterface $moduleList,
        Repository $assetRepo,
        Source $assetSource,
        NotificationCollectionFactory $notificationFactory,
        Config $taxConfig,
        Calculation $taxCalculation,
        AdyenLogger $adyenLogger,
        StoreManagerInterface $storeManager,
        CacheInterface $cache,
        ScopeConfigInterface $config,
        ConfigHelper $configHelper,
        PlatformInfo $platformInfo,
        RequestInterface $request,
    ) {
        parent::__construct($context);
        $this->_encryptor = $encryptor;
        $this->_dataStorage = $dataStorage;
        $this->_country = $country;
        $this->_moduleList = $moduleList;
        $this->_assetRepo = $assetRepo;
        $this->_assetSource = $assetSource;
        $this->_notificationFactory = $notificationFactory;
        $this->_taxConfig = $taxConfig;
        $this->_taxCalculation = $taxCalculation;
        $this->adyenLogger = $adyenLogger;
        $this->storeManager = $storeManager;
        $this->cache = $cache;
        $this->config = $config;
        $this->configHelper = $configHelper;
        $this->platformInfo = $platformInfo;
        $this->_request = $request;
    }

    /**
     * return recurring types for configuration setting
     *
     * @return array
     */
    public function getRecurringTypes()
    {
        return [
            RecurringType::ONECLICK => 'ONECLICK',
            RecurringType::ONECLICK_RECURRING => 'ONECLICK,RECURRING',
            RecurringType::RECURRING => 'RECURRING'
        ];
    }

    /**
     * return Checkout frontend regions for configuration setting
     *
     * @return array
     */
    public function getCheckoutFrontendRegions()
    {
        return [
            'eu' => 'Default (EU - Europe)',
            'au' => 'AU - Australasia',
            'us' => 'US - United States',
            'in' => 'IN - India',
            'apse' => 'APSE - Asia Pacific Southeast'
        ];
    }

    /**
     * return capture modes for configuration setting
     *
     * @return array
     */
    public function getCaptureModes()
    {
        return [
            'auto' => 'Immediate',
            'manual' => 'Manual'
        ];
    }

    public function getOpenInvoiceCaptureModes()
    {
        return [
            'auto' => 'Immediate',
            'manual' => 'Manual',
            'onshipment' => 'On shipment'
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
        if ($amount === null) {
            // PHP 8 does not accept first param to be NULL
            $amount = 0;
        }
        return (int)number_format($amount, $this->decimalNumbers($currency), '', '');
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

    public function isMotoDemoMode(array $motoMerchantAccountProperties): bool
    {
        return $motoMerchantAccountProperties['demo_mode'] === '1';
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
     * Create a file asset that's subject of fallback system
     *
     * @param string $fileId
     * @param array $params
     * @return File
     * @throws LocalizedException
     */
    public function createAsset($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->_request->isSecure()], $params);
        return $this->_assetRepo->createAsset($fileId, $params);
    }

    public function getCustomerStreetLinesEnabled($storeId)
    {
        $path = 'customer/address/street_lines';

        return $this->config->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getUnprocessedNotifications()
    {
        $notifications = $this->_notificationFactory->create();
        $notifications->unprocessedNotificationsFilter();
        return $notifications->getSize();
    }

    /**
     * @param null|int|string $storeId
     * @return string the X API Key for the specified or current store
     */
    public function getPosApiKey($storeId = null)
    {
        if ($this->configHelper->isDemoMode($storeId)) {
            $encryptedApiKeyTest = $this->configHelper->getAdyenPosCloudConfigData('api_key_test', $storeId);
            if (is_null($encryptedApiKeyTest)) {
                return null;
            }

            $apiKey = $this->_encryptor->decrypt(trim((string) $encryptedApiKeyTest));
        } else {
            $encryptedApiKeyLive = $this->configHelper->getAdyenPosCloudConfigData('api_key_live', $storeId);
            if (is_null($encryptedApiKeyLive)) {
                return null;
            }

            $apiKey = $this->_encryptor->decrypt(trim((string) $encryptedApiKeyLive));
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
        return $this->configHelper->getAdyenPosCloudConfigData('pos_store_id', $storeId);
    }

    /**
     * Return the merchant account name configured for the proper payment method.
     * If it is not configured for the specific payment method,
     * return the merchant account name defined in required settings.
     *
     * @param $paymentMethod
     * @param null|int|string $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getAdyenMerchantAccount($paymentMethod, $storeId = null)
    {
        if (!$storeId) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $merchantAccount = $this->configHelper->getAdyenAbstractConfigData("merchant_account", $storeId);
        $merchantAccountPos = $this->configHelper->getAdyenPosCloudConfigData('pos_merchant_account', $storeId);

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
    public function formatTerminalAPIReceipt($paymentReceipt): string
    {
        $formattedHtml = "<table class='terminal-api-receipt'>";
        foreach ($paymentReceipt as $receipt) {
            if ($receipt['DocumentQualifier'] == "CustomerReceipt") {
                foreach ($receipt['OutputContent']['OutputText'] as $item) {
                    parse_str((string) $item['Text'], $textParts);
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
     * @param $storeId
     * @param $apiKey
     * @param $motoMerchantAccount
     * @param bool|null $demoMode
     * @return Client
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function initializeAdyenClient(
        $storeId = null,
        $apiKey = null,
        $motoMerchantAccount = null,
        ?bool $demoMode = null
    ): Client {
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $isDemo = is_null($demoMode) ? $this->configHelper->isDemoMode($storeId) : $demoMode;
        $mode = $isDemo ? 'test' : 'live';
        if (empty($apiKey)) {
            $apiKey = $this->configHelper->getAPIKey($mode, $storeId);
        }

        if (!is_null($motoMerchantAccount)) {
            try {
                $motoMerchantAccountProperties = $this->configHelper->getMotoMerchantAccountProperties(
                    $motoMerchantAccount,
                    $storeId
                );
            } catch (AdyenException $e) {
                $this->adyenLogger->addAdyenDebug($e->getMessage());
                throw $e;
            }

            // Override the x-api-key and demo mode setting if MOTO merchant account is set.
            $apiKey = $this->_encryptor->decrypt($motoMerchantAccountProperties['apikey']);
            $isDemo = $this->isMotoDemoMode($motoMerchantAccountProperties);
        }

        $client = $this->createAdyenClient();

        $client->setApplicationName(self::APPLICATION_NAME);
        $client->setXApiKey($apiKey);
        $client->setMerchantApplication($this->platformInfo->getModuleName(), $this->platformInfo->getModuleVersion());
        $platformData = $this->platformInfo->getMagentoDetails();

        $hasPlatformIntegrator = $this->configHelper->getHasPlatformIntegrator();
        $platformIntegratorName = $this->configHelper->getPlatformIntegratorName();
        $platformIntegrator = ($hasPlatformIntegrator && $platformIntegratorName) ? $platformIntegratorName : '';
        $client->setExternalPlatform($platformData['name'], $platformData['version'], $platformIntegrator);

        if ($isDemo) {
            $client->setEnvironment(Environment::TEST);
        } else {
            $client->setEnvironment(Environment::LIVE, $this->configHelper->getLiveEndpointPrefix($storeId));
        }

        return $client;
    }

    /**
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function initializeAdyenClientWithClientConfig(array $clientConfig): Client
    {
        $storeId = $clientConfig['storeId'];
        $motoMerchantAccount = null;

        if (isset($clientConfig['isMotoTransaction']) && $clientConfig['isMotoTransaction'] === true) {
            $motoMerchantAccount = $clientConfig['motoMerchantAccount'];
        }

        return $this->initializeAdyenClient($storeId, null, $motoMerchantAccount);
    }

    public function initializePaymentsApi(Client $client):PaymentsApi
    {
        return new PaymentsApi($client);
    }

    public function initializeModificationsApi(Client $client):ModificationsApi
    {
        return new ModificationsApi($client);
    }

    public function initializeRecurringApi(Client $client):RecurringApi
    {
        return new RecurringApi($client);
    }

    public function initializeOrdersApi(Client $client): OrdersApi
    {
        return new OrdersApi($client);
    }

    public function initializePaymentLinksApi(Client $client):PaymentLinksApi
    {
        return new PaymentLinksApi($client);
    }

    public function initializeDonationsApi(Client $client):DonationsApi
    {
        return new DonationsApi($client);
    }

    /**
     * @param Client $client
     * @return PosPayment
     * @throws AdyenException
     */
    public function createAdyenPosPaymentService(Client $client): PosPayment
    {
        return new PosPayment($client);
    }

    /**
     * @return Client
     */
    private function createAdyenClient(): Client
    {
        return new Client();
    }

    /**
     * @param null|int|string $storeId
     * @return string
     */
    public function getCheckoutEnvironment($storeId = null): string
    {
        if ($this->configHelper->isDemoMode($storeId)) {
            return self::TEST;
        }

        switch ($this->configHelper->getCheckoutFrontendRegion($storeId)) {
            case "au":
                return self::LIVE_AU;
            case "us":
                return self::LIVE_US;
            case "in":
                return self::LIVE_IN;
            default:
                return self::LIVE;
        }
    }

    /**
     * Get icon from variant
     *
     * @param $variant
     * @return array
     * @throws LocalizedException
     */
    public function getVariantIcon($variant): array
    {
        $asset = $this->createAsset(sprintf("Adyen_Payment::images/logos/%s_small.png", $variant));

        if($this->_assetSource->findSource($asset)) {
            list($width, $height) = getimagesize($asset->getSourceFile());
            $icon = ['url' => $asset->getUrl(), 'width' => $width, 'height' => $height];
        } else {
            $url = "https://checkoutshopper-test.adyen.com/checkoutshopper/images/logos/$variant.svg";
            $icon = ['url' => $url, 'width' => 77, 'height' => 50];
        }

        return $icon;
    }

    /**
     * Get the Customer Area PSP Search URL with a preset PSP Reference
     *
     * @param string $pspReference
     * @param string $liveEnvironment
     * @return string
     */
    public function getPspReferenceSearchUrl($pspReference, $liveEnvironment): string
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

    public function logRequest(array $request, $apiVersion, $endpoint)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isDemo = $this->configHelper->isDemoMode($storeId);
        $context = ['apiVersion' => $apiVersion];
        if ($isDemo) {
            $context['body'] = $request;
        } else {
            $context['livePrefix'] = $this->configHelper->getLiveEndpointPrefix($storeId);
            $context['body'] = $this->filterReferences($request);
        }

        $this->adyenLogger->addAdyenInfoLog('Request to Adyen API ' . $endpoint, $context);
    }

    public function logResponse(array $response)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isDemo = $this->configHelper->isDemoMode($storeId);
        $context = [];
        if ($isDemo) {
            $context['body'] = $response;
        } else {
            $context['body'] = $this->filterReferences($response);
        }

        $this->adyenLogger->addAdyenInfoLog('Response from Adyen API', $context);
    }

    public function logAdyenException(AdyenException $e)
    {
        $responseArray = [];
        $responseArray['error'] = $e->getMessage();
        $responseArray['errorCode'] = $e->getAdyenErrorCode();
        $this->logResponse($responseArray);
    }

    private function filterReferences(array $data): array
    {
        return array_filter($data, function($value, $key) {
            // Keep only reference keys, e.g. reference, pspReference, merchantReference etc.
            return false !== strpos(strtolower($key), 'reference');
        }, ARRAY_FILTER_USE_BOTH);
    }
}

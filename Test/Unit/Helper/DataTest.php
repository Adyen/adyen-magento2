<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\Notification\Collection;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Service\Checkout\DonationsApi;
use Adyen\Service\PosPayment;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Config\DataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use PHPUnit\Framework\Attributes\Test;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\MockObject\Exception;
use Magento\Store\Model\ScopeInterface;
use Adyen\Config as AdyenConfig;
use Adyen\Service\Checkout\ModificationsApi;
use Adyen\Service\Checkout\OrdersApi;
use Adyen\Service\Checkout\PaymentLinksApi;
use Adyen\Service\Checkout\PaymentsApi;
use Adyen\Service\RecurringApi;

class DataTest extends AbstractAdyenTestCase
{
    private Data $dataHelper;

    private Context $context;
    private EncryptorInterface $encryptor;
    private DataInterface $dataStorage;
    private Country $country;
    private ModuleListInterface $moduleList;
    private Repository $assetRepo;
    private Source $assetSource;
    private CollectionFactory $notificationFactory;
    private Config $taxConfig;
    private Calculation $taxCalculation;
    private AdyenLogger $adyenLogger;
    private StoreManagerInterface $storeManager;
    private CacheInterface $cache;
    private ScopeConfigInterface $scopeConfig;
    private ConfigHelper $configHelper;
    private PlatformInfo $platformInfo;
    private Client $clientMock;
    private AdyenConfig $adyenConfig;
    private Store $store;
    private RequestInterface $request;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->clientMock = $this->createConfiguredMock(Client::class, [
                'getConfig' => new AdyenConfig([
                    'environment' => 'test',
                    'externalPlatform' => ['name' => 'test platform', 'version' => '1.2.3', 'integrator' => 'test integrator'],
                    'merchantApplication' => ['name' => 'test merchant', 'version' => '1.2.3'],
                    'adyenPaymentSource' => ['name' => 'test source', 'version' => '1.2.3']
                ]),
                'getLibraryName' => 'test library',
                'getLibraryVersion' => '1.2.3',
            ]
        );

        $this->context = $this->createMock(Context::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->dataStorage = $this->createMock(DataInterface::class);
        $this->country = $this->createMock(Country::class);
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->assetRepo = $this->createMock(Repository::class);
        $this->assetSource = $this->createMock(Source::class);
        $this->notificationFactory = $this->createMock(CollectionFactory::class);
        $this->taxConfig = $this->createMock(Config::class);
        $this->taxCalculation = $this->createMock(Calculation::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->platformInfo = $this->createMock(PlatformInfo::class);
        $this->store = $this->createMock(Store::class);
        $this->request = $this->createMock(RequestInterface::class);

        $this->dataHelper = new Data(
            $this->context,
            $this->encryptor,
            $this->dataStorage,
            $this->country,
            $this->moduleList,
            $this->assetRepo,
            $this->assetSource,
            $this->notificationFactory,
            $this->taxConfig,
            $this->taxCalculation,
            $this->adyenLogger,
            $this->storeManager,
            $this->cache,
            $this->scopeConfig,
            $this->configHelper,
            $this->platformInfo,
            $this->request
        );
    }

    #[Test]
    public function testDecimalNumbers(): void
    {
        self::assertSame(0, $this->dataHelper->decimalNumbers('JPY'));
        self::assertSame(3, $this->dataHelper->decimalNumbers('KWD'));
        self::assertSame(2, $this->dataHelper->decimalNumbers('USD'));
    }

    #[Test]
    public function testFormatAmount(): void
    {
        self::assertSame(1000, $this->dataHelper->formatAmount(10, 'USD'));
        self::assertSame(123456, $this->dataHelper->formatAmount(1234.56, 'USD'));
        self::assertSame(0, $this->dataHelper->formatAmount(null, 'USD'));
    }

    #[Test]
    public function testOriginalAmount(): void
    {
        self::assertSame(0.10, $this->dataHelper->originalAmount(100, 'KWD'));
        self::assertSame(1, $this->dataHelper->originalAmount(100, 'USD')); // Fallback 2 decimals
    }

    #[Test]
    public function testIsMotoDemoMode(): void
    {
        $motoMerchantAccountProperties = ['demo_mode' => '1'];
        self::assertTrue($this->dataHelper->isMotoDemoMode($motoMerchantAccountProperties));

        $motoMerchantAccountProperties = ['demo_mode' => '0'];
        self::assertFalse($this->dataHelper->isMotoDemoMode($motoMerchantAccountProperties));
    }

    #[Test]
    public function testGetCheckoutFrontendRegions(): void
    {
        $regions = $this->dataHelper->getCheckoutFrontendRegions();
        self::assertArrayHasKey('eu', $regions);
        self::assertArrayHasKey('au', $regions);
    }

    #[Test]
    public function testGetCaptureModes(): void
    {
        $modes = $this->dataHelper->getCaptureModes();
        self::assertSame(['auto' => 'Immediate', 'manual' => 'Manual'], $modes);
    }

    #[Test]
    public function testGetOpenInvoiceCaptureModes(): void
    {
        $modes = $this->dataHelper->getOpenInvoiceCaptureModes();
        self::assertSame(['auto' => 'Immediate', 'manual' => 'Manual', 'onshipment' => 'On shipment'], $modes);
    }

    #[Test]
    public function testGetPspReferenceSearchUrl(): void
    {
        $urlLive = $this->dataHelper->getPspReferenceSearchUrl('12345', 'true');
        self::assertStringContainsString('ca-live', $urlLive);

        $urlTest = $this->dataHelper->getPspReferenceSearchUrl('12345', 'false');
        self::assertStringContainsString('ca-test', $urlTest);
    }

    #[Test]
    public function testInitializeAdyenClientInDemoMode(): void
    {
        $storeId = 1;
        $apiKey = 'demo_api_key';
        $decryptedKey = 'decrypted_demo_key';

        $this->store->method('getId')->willReturn($storeId);

        $this->storeManager
            ->method('getStore')
            ->willReturn($this->store);

        $this->configHelper
            ->method('isDemoMode')
            ->with($storeId)
            ->willReturn(true);

        $this->configHelper
            ->method('getAPIKey')
            ->with('test', $storeId)
            ->willReturn($apiKey);

        $this->encryptor
            ->method('decrypt')
            ->willReturn($decryptedKey);

        $this->platformInfo
            ->method('getModuleName')
            ->willReturn('Adyen_Payment');

        $this->platformInfo
            ->method('getModuleVersion')
            ->willReturn('9.0.0');

        $this->platformInfo
            ->method('getMagentoDetails')
            ->willReturn(['name' => 'Magento', 'version' => '2.4.6']);

        $this->scopeConfig
            ->method('getValue')
            ->willReturn(null); // no external integrator

        $client = $this->dataHelper->initializeAdyenClient($storeId);

        self::assertInstanceOf(Client::class, $client);
        //self::assertSame('Magento 2 plugin', $client->getApplicationName());
    }

    #[Test]
    public function testGetAdyenMerchantAccountPosMethod(): void
    {
        $storeId = 10;
        $merchantAccount = 'default_account';
        $posMerchantAccount = 'pos_account';

        $this->store->method('getId')->willReturn($storeId);

        $this->storeManager
            ->method('getStore')
            ->willReturn($this->store);

        $this->configHelper
            ->method('getAdyenAbstractConfigData')
            ->with('merchant_account', $storeId)
            ->willReturn($merchantAccount);

        $this->configHelper
            ->method('getAdyenPosCloudConfigData')
            ->with('pos_merchant_account', $storeId)
            ->willReturn($posMerchantAccount);

        $result = $this->dataHelper->getAdyenMerchantAccount('adyen_pos_cloud', $storeId);

        self::assertSame($posMerchantAccount, $result);
    }

    #[Test]
    public function testGetAdyenMerchantAccountDefault(): void
    {
        $storeId = 5;
        $merchantAccount = 'default_account';

        $this->store->method('getId')->willReturn($storeId);

        $this->storeManager
            ->method('getStore')
            ->willReturn($this->store);

        $this->configHelper
            ->method('getAdyenAbstractConfigData')
            ->with('merchant_account', $storeId)
            ->willReturn($merchantAccount);

        $this->configHelper
            ->method('getAdyenPosCloudConfigData')
            ->with('pos_merchant_account', $storeId)
            ->willReturn(null);

        $result = $this->dataHelper->getAdyenMerchantAccount('adyen_card', $storeId);

        self::assertSame($merchantAccount, $result);
    }

    #[Test]
    public function testLogRequestInDemoMode(): void
    {
        $storeId = 3;
        $apiVersion = 'v69';
        $endpoint = '/payments';
        $request = ['reference' => 'ABC123', 'amount' => ['value' => 1000, 'currency' => 'EUR']];

        $this->store->method('getId')->willReturn($storeId);

        $this->storeManager
            ->method('getStore')
            ->willReturn($this->store);

        $this->configHelper
            ->method('isDemoMode')
            ->with($storeId)
            ->willReturn(true);

        $this->adyenLogger
            ->expects(self::once())
            ->method('addAdyenInfoLog')
            ->with(
                self::stringContains('Request to Adyen API'),
                self::callback(function ($context) use ($apiVersion, $request) {
                    return $context['apiVersion'] === $apiVersion && $context['body'] === $request;
                })
            );

        $this->dataHelper->logRequest($request, $apiVersion, $endpoint);
    }

    #[Test]
    public function testLogResponseInDemoMode(): void
    {
        $storeId = 1;
        $response = ['pspReference' => 'ABC123', 'resultCode' => 'Authorised'];

        $this->store->method('getId')->willReturn($storeId);

        $this->storeManager
            ->method('getStore')
            ->willReturn($this->store);

        $this->configHelper
            ->method('isDemoMode')
            ->with($storeId)
            ->willReturn(true);

        $this->adyenLogger
            ->expects(self::once())
            ->method('addAdyenInfoLog')
            ->with(
                'Response from Adyen API',
                ['body' => $response]
            );

        $this->dataHelper->logResponse($response);
    }

    #[Test]
    public function testInitializeAdyenClientWithClientConfigMoto(): void
    {
        $storeId = 3;
        $motoMerchantAccount = 'motoAccount';
        $apiKeyEncrypted = 'encrypted_key';
        $apiKeyDecrypted = 'decrypted_key';

        $motoProperties = [
            'apikey' => $apiKeyEncrypted,
            'demo_mode' => '1'
        ];

        $this->store->method('getId')->willReturn($storeId);

        $this->storeManager
            ->method('getStore')
            ->willReturn($this->store);

        $this->configHelper
            ->method('getMotoMerchantAccountProperties')
            ->with($motoMerchantAccount, $storeId)
            ->willReturn($motoProperties);

        $this->encryptor
            ->method('decrypt')
            ->with($apiKeyEncrypted)
            ->willReturn($apiKeyDecrypted);

        $this->platformInfo
            ->method('getModuleName')
            ->willReturn('Adyen_Payment');
        $this->platformInfo
            ->method('getModuleVersion')
            ->willReturn('9.0.0');
        $this->platformInfo
            ->method('getMagentoDetails')
            ->willReturn(['name' => 'Magento', 'version' => '2.4.6']);

        $this->scopeConfig
            ->method('getValue')
            ->willReturn(null);

        $client = $this->dataHelper->initializeAdyenClientWithClientConfig([
            'storeId' => $storeId,
            'isMotoTransaction' => true,
            'motoMerchantAccount' => $motoMerchantAccount
        ]);

        self::assertInstanceOf(Client::class, $client);
    }

    #[Test]
    public function testGetAdyenCcTypes(): void
    {
        $creditCardTypes = ['mc' => ['code' => 'MC', 'code_alt' => 'mastercard']];
        $this->dataStorage
            ->expects(self::once())
            ->method('get')
            ->with('adyen_credit_cards')
            ->willReturn($creditCardTypes);

        $result = $this->dataHelper->getAdyenCcTypes();
        self::assertSame($creditCardTypes, $result);
    }

    #[Test]
    public function testGetCcTypesAltData(): void
    {
        $creditCardTypes = [
            'mc' => ['code' => 'MC', 'code_alt' => 'mastercard'],
            'visa' => ['code' => 'VISA', 'code_alt' => 'visa']
        ];

        $this->dataStorage
            ->method('get')
            ->with('adyen_credit_cards')
            ->willReturn($creditCardTypes);

        $result = $this->dataHelper->getCcTypesAltData();

        self::assertArrayHasKey('mastercard', $result);
        self::assertSame('mc', $result['mastercard']['code']);
        self::assertArrayHasKey('visa', $result);
        self::assertSame('visa', $result['visa']['code']);
    }

    #[Test]
    public function testGetMagentoCreditCartType(): void
    {
        $adyenCcTypes = [
            'amex' => ['code' => 'AE', 'code_alt' => 'american_express']
        ];

        $this->dataStorage
            ->method('get')
            ->with('adyen_credit_cards')
            ->willReturn($adyenCcTypes);

        $result = $this->dataHelper->getMagentoCreditCartType('american_express');

        self::assertSame('amex', $result);
    }

    #[Test]
    public function testGetCustomerStreetLinesEnabled(): void
    {
        $storeId = 99;

        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with('customer/address/street_lines', ScopeInterface::SCOPE_STORE, $storeId)
            ->willReturn(2);

        $result = $this->dataHelper->getCustomerStreetLinesEnabled($storeId);

        self::assertSame(2, $result);
    }

    #[Test]
    public function testGetUnprocessedNotifications(): void
    {
        $mockCollection = $this->createMock(Collection::class);

        $mockCollection
            ->expects(self::once())
            ->method('unprocessedNotificationsFilter');

        $mockCollection
            ->expects(self::once())
            ->method('getSize')
            ->willReturn(5);

        $this->notificationFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn($mockCollection);

        $result = $this->dataHelper->getUnprocessedNotifications();

        self::assertSame(5, $result);
    }

    #[Test]
    public function testGetPosApiKeyInDemoMode(): void
    {
        $storeId = 1;
        $encryptedKey = 'encrypted_test_key';
        $decryptedKey = 'decrypted_test_key';

        $this->configHelper
            ->method('isDemoMode')
            ->with($storeId)
            ->willReturn(true);

        $this->configHelper
            ->method('getAdyenPosCloudConfigData')
            ->with('api_key_test', $storeId)
            ->willReturn($encryptedKey);

        $this->encryptor
            ->expects(self::once())
            ->method('decrypt')
            ->with($encryptedKey)
            ->willReturn($decryptedKey);

        $result = $this->dataHelper->getPosApiKey($storeId);

        self::assertSame($decryptedKey, $result);
    }

    #[Test]
    public function testGetPosApiKeyInLiveMode(): void
    {
        $storeId = 2;
        $encryptedKey = 'encrypted_live_key';
        $decryptedKey = 'decrypted_live_key';

        $this->configHelper
            ->method('isDemoMode')
            ->with($storeId)
            ->willReturn(false);

        $this->configHelper
            ->method('getAdyenPosCloudConfigData')
            ->with('api_key_live', $storeId)
            ->willReturn($encryptedKey);

        $this->encryptor
            ->expects(self::once())
            ->method('decrypt')
            ->with($encryptedKey)
            ->willReturn($decryptedKey);

        $result = $this->dataHelper->getPosApiKey($storeId);

        self::assertSame($decryptedKey, $result);
    }

    #[Test]
    public function testGetPosStoreId(): void
    {
        $storeId = 3;
        $posStoreId = 'POS_STORE_123';

        $this->configHelper
            ->expects(self::once())
            ->method('getAdyenPosCloudConfigData')
            ->with('pos_store_id', $storeId)
            ->willReturn($posStoreId);

        $result = $this->dataHelper->getPosStoreId($storeId);

        self::assertSame($posStoreId, $result);
    }

    #[Test]
    public function testFormatTerminalAPIReceipt(): void
    {
        $receipt = [
            [
                'DocumentQualifier' => 'CustomerReceipt',
                'OutputContent' => [
                    'OutputText' => [
                        ['Text' => 'name=Item1&value=10.00'],
                        ['Text' => 'name=Item2&value=20.00'],
                    ]
                ]
            ]
        ];

        $result = $this->dataHelper->formatTerminalAPIReceipt($receipt);

        self::assertStringContainsString('<table', $result);
        self::assertStringContainsString('Item1', $result);
        self::assertStringContainsString('10.00', $result);
        self::assertStringContainsString('Item2', $result);
        self::assertStringContainsString('20.00', $result);
    }

    #[Test]
    public function testInitializeAdyenClientInLiveMode(): void
    {
        $storeId = 2;
        $apiKey = 'live_api_key';
        $decryptedKey = 'decrypted_live_key';
        $livePrefix = 'live-prefix';

        $this->store->method('getId')->willReturn($storeId);

        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->configHelper->method('isDemoMode')->with($storeId)->willReturn(false);
        $this->configHelper->method('getAPIKey')->with('live', $storeId)->willReturn($apiKey);
        $this->encryptor->method('decrypt')->willReturn($decryptedKey);

        $this->platformInfo->method('getModuleName')->willReturn('Adyen_Payment');
        $this->platformInfo->method('getModuleVersion')->willReturn('9.0.0');
        $this->platformInfo->method('getMagentoDetails')->willReturn(['name' => 'Magento', 'version' => '2.4.6']);
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->configHelper->method('getLiveEndpointPrefix')->with($storeId)->willReturn($livePrefix);

        $client = $this->dataHelper->initializeAdyenClient($storeId);

        self::assertInstanceOf(Client::class, $client);
    }

    #[Test]
    public function testInitializeAdyenClientWithClientConfigNonMoto(): void
    {
        $storeId = 7;
        $apiKey = 'some_api_key';

        $this->store->method('getId')->willReturn($storeId);

        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->configHelper->method('isDemoMode')->with($storeId)->willReturn(true);
        $this->configHelper->method('getAPIKey')->with('test', $storeId)->willReturn($apiKey);
        $this->encryptor->method('decrypt')->willReturn($apiKey);

        $this->platformInfo->method('getModuleName')->willReturn('Adyen_Payment');
        $this->platformInfo->method('getModuleVersion')->willReturn('9.0.0');
        $this->platformInfo->method('getMagentoDetails')->willReturn(['name' => 'Magento', 'version' => '2.4.6']);
        $this->scopeConfig->method('getValue')->willReturn(null);

        $client = $this->dataHelper->initializeAdyenClientWithClientConfig([
            'storeId' => $storeId
        ]);

        self::assertInstanceOf(Client::class, $client);
    }

    #[Test]
    public function testInitializePaymentsApi()
    {
        $service = $this->dataHelper->initializePaymentsApi($this->clientMock);
        self::assertInstanceOf(PaymentsApi::class, $service);
    }

    #[Test]
    public function testInitializeModificationsApi()
    {
        $service = $this->dataHelper->initializeModificationsApi($this->clientMock);
        self::assertInstanceOf(ModificationsApi::class, $service);
    }

    #[Test]
    public function testInitializeRecurringApi()
    {
        $service = $this->dataHelper->initializeRecurringApi($this->clientMock);
        self::assertInstanceOf(RecurringApi::class, $service);
    }

    #[Test]
    public function testInitializeOrdersApi()
    {
        $service = $this->dataHelper->initializeOrdersApi($this->clientMock);
        self::assertInstanceOf(OrdersApi::class, $service);
    }

    #[Test]
    public function testInitializePaymentLinksApi()
    {
        $service = $this->dataHelper->initializePaymentLinksApi($this->clientMock);
        self::assertInstanceOf(PaymentLinksApi::class, $service);
    }

    #[Test]
    public function testInitializeDonationsApi()
    {
        $service = $this->dataHelper->initializeDonationsApi($this->clientMock);
        self::assertInstanceOf(DonationsApi::class, $service);
    }


    /**
     * @throws AdyenException
     */
    #[Test]
    public function testCreateAdyenPosPaymentService()
    {
        $service = $this->dataHelper->createAdyenPosPaymentService($this->clientMock);
        self::assertInstanceOf(PosPayment::class, $service);
    }

    #[Test]
    public function testGetCheckoutEnvironmentDemoMode()
    {
        $this->configHelper->expects($this->once())
            ->method('isDemoMode')
            ->willReturn(true);

        // Call the method under test
        $result = $this->dataHelper->getCheckoutEnvironment();

        // Assert the expected result
        $this->assertEquals(Data::TEST, $result);
    }

    #[Test]
    public function testGetCheckoutEnvironmentLiveAU()
    {
        // Setup the mock to return false for isDemoMode
        $this->configHelper->expects($this->once())
            ->method('isDemoMode')
            ->willReturn(false);

        // Setup the mock to return "au" for getCheckoutFrontendRegion
        $this->configHelper->expects($this->once())
            ->method('getCheckoutFrontendRegion')
            ->willReturn("au");

        // Call the method under test
        $result = $this->dataHelper->getCheckoutEnvironment();

        // Assert the expected result
        $this->assertEquals(Data::LIVE_AU, $result);
    }

    #[Test]
    public function testGetCheckoutEnvironmentLiveUS()
    {
        // Setup the mock to return false for isDemoMode
        $this->configHelper->expects($this->once())
            ->method('isDemoMode')
            ->willReturn(false);

        // Setup the mock to return "us" for getCheckoutFrontendRegion
        $this->configHelper->expects($this->once())
            ->method('getCheckoutFrontendRegion')
            ->willReturn("us");

        // Call the method under test
        $result = $this->dataHelper->getCheckoutEnvironment();

        // Assert the expected result
        $this->assertEquals(Data::LIVE_US, $result);
    }

    #[Test]
    public function testGetCheckoutEnvironmentLiveIN()
    {
        // Setup the mock to return false for isDemoMode
        $this->configHelper->expects($this->once())
            ->method('isDemoMode')
            ->willReturn(false);

        // Setup the mock to return "in" for getCheckoutFrontendRegion
        $this->configHelper->expects($this->once())
            ->method('getCheckoutFrontendRegion')
            ->willReturn("in");

        // Call the method under test
        $result = $this->dataHelper->getCheckoutEnvironment();

        // Assert the expected result
        $this->assertEquals(Data::LIVE_IN, $result);
    }

    #[Test]
    public function testGetCheckoutEnvironmentDefault()
    {
        // Setup the mock to return false for isDemoMode
        $this->configHelper->expects($this->once())
            ->method('isDemoMode')
            ->willReturn(false);

        // Setup the mock to return a value other than "au", "us", or "in" for getCheckoutFrontendRegion
        $this->configHelper->expects($this->once())
            ->method('getCheckoutFrontendRegion')
            ->willReturn("eu");

        // Call the method under test
        $result = $this->dataHelper->getCheckoutEnvironment();

        // Assert the expected result
        $this->assertEquals(Data::LIVE, $result);
    }

    #[Test]
    public function testCreateAsset(): void
    {
        $fileId = 'Adyen_Payment::images/logos/adyen_vi.png';
        $mockAsset = $this->createMock(\Magento\Framework\View\Asset\File::class);

        // Mock request
        $mockRequest = $this->createMock(\Magento\Framework\App\RequestInterface::class);
        $mockRequest->method('isSecure')->willReturn(true);

        // Context returns mocked request
        $this->context
            ->method('getRequest')
            ->willReturn($mockRequest);

        $this->assetRepo
            ->expects(self::once())
            ->method('createAsset')
            ->with($fileId, ['_secure' => null])
            ->willReturn($mockAsset);

        $result = $this->dataHelper->createAsset($fileId);

        self::assertSame($mockAsset, $result);
    }

}

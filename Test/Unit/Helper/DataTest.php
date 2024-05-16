<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Client;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Config\Source\RenderMode;
use Adyen\Payment\Model\RecurringType;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory as NotificationCollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Config\DataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Store\Model\StoreManager;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Magento\Sales\Model\Order;
use Magento\Framework\View\Asset\File;
use ReflectionClass;

class DataTest extends AbstractAdyenTestCase
{
    /**
     * @var Data
     */
    private $dataHelper;

    public function setUp(): void
    {
        // Prepare mock data for ccTypesAltData
        $this->ccTypesAltData = [
            'VI' => ['code_alt' => 'VI', 'code' => 'VI'],
        ];

        $this->configHelper = $this->createConfiguredMock(ConfigHelper::class, [
            'getMotoMerchantAccountProperties' => [
                'apikey' => 'wellProtectedEncryptedApiKey',
                'demo_mode' => '1'
            ]
        ]);
        $context = $this->createMock(Context::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->dataStorage = $this->createMock(DataInterface::class);
        $country = $this->createMock(Country::class);
        $moduleList = $this->createMock(ModuleListInterface::class);
        $assetRepo = $this->createMock(Repository::class);
        $assetSource = $this->createMock(Source::class);
        $notificationFactory = $this->createGeneratedMock(NotificationCollectionFactory::class);
        $taxConfig = $this->createMock(Config::class);
        $taxCalculation = $this->createMock(Calculation::class);
        $backendHelper = $this->createMock(BackendHelper::class);
        $productMetadata = $this->createConfiguredMock(ProductMetadata::class, [
            'getName' => 'magento',
            'getVersion' => '2.x.x',
            'getEdition' => 'Community'
        ]);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->storeManager = $this->createMock(StoreManager::class);
        $cache = $this->createMock(CacheInterface::class);
        $this->localeResolver = $this->createMock(ResolverInterface::class);
        $this->config = $this->createMock(ScopeConfigInterface::class);
        $componentRegistrar = $this->createConfiguredMock(ComponentRegistrarInterface::class, [
            'getPath' => 'vendor/adyen/module-payment'
        ]);
        $this->localeHelper = $this->createMock(Locale::class);
        $this->orderManagement = $this->createMock(OrderManagementInterface::class);
        $this->orderStatusHistoryFactory = $this->createGeneratedMock(HistoryFactory::class);

        $this->dataStorage->method('get')
            ->with('adyen_credit_cards')
            ->willReturn($this->ccTypesAltData);

        // Partial mock builder is being used for mocking the methods in the class being tested.
        $this->dataHelper = $this->getMockBuilder(Data::class)
            ->setMethods(['getModuleVersion'])
            ->setConstructorArgs([
                $context,
                $this->encryptor,
                $this->dataStorage,
                $country,
                $moduleList,
                $assetRepo,
                $assetSource,
                $notificationFactory,
                $taxConfig,
                $taxCalculation,
                $backendHelper,
                $productMetadata,
                $this->adyenLogger,
                $this->storeManager,
                $cache,
                $this->localeResolver,
                $this->config,
                $componentRegistrar,
                $this->localeHelper,
                $this->orderManagement,
                $this->orderStatusHistoryFactory,
                $this->configHelper
            ])
            ->getMock();

        $this->dataHelper->expects($this->any())
            ->method('getModuleVersion')
            ->willReturn('1.2.3');
    }

    public function testGetRecurringTypes()
    {
        // Define the expected result
        $expectedResult = [
            RecurringType::ONECLICK => 'ONECLICK',
            RecurringType::ONECLICK_RECURRING => 'ONECLICK,RECURRING',
            RecurringType::RECURRING => 'RECURRING'
        ];

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getRecurringTypes();

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function testGetCheckoutFrontendRegions()
    {
        // Define the expected result
        $expectedResult = [
            'eu' => 'Default (EU - Europe)',
            'au' => 'AU - Australasia',
            'us' => 'US - United States',
            'in' => 'IN - India'
        ];

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getCheckoutFrontendRegions();

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function testGetCaptureModes()
    {
        // Define the expected result for getCaptureModes
        $expectedResult = [
            'auto' => 'Immediate',
            'manual' => 'Manual'
        ];

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getCaptureModes();

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test getOpenInvoiceCaptureModes method
     */
    public function testGetOpenInvoiceCaptureModes()
    {
        // Define the expected result for getOpenInvoiceCaptureModes
        $expectedResult = [
            'auto' => 'Immediate',
            'manual' => 'Manual',
            'onshipment' => 'On shipment'
        ];

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getOpenInvoiceCaptureModes();

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function testGetPaymentRoutines()
    {
        // Define the expected result
        $expectedResult = [
            'single' => 'Single Page Payment Routine',
            'multi' => 'Multi-page Payment Routine'
        ];

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getPaymentRoutines();

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test getMinorUnitTaxPercent method
     */
    public function testGetMinorUnitTaxPercent()
    {
        // Define a tax percentage
        $taxPercent = 0.070; // 7%

        // Define the expected result
        $expectedResult = (int)($taxPercent * 100); // 700

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getMinorUnitTaxPercent($taxPercent);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function testFormatAmount()
    {
        $this->assertEquals('1234', $this->dataHelper->formatAmount('12.34', 'EUR'));
        $this->assertEquals('1200', $this->dataHelper->formatAmount('12.00', 'USD'));
        $this->assertEquals('12', $this->dataHelper->formatAmount('12.00', 'JPY'));
    }

    public function testisPaymentMethodOpenInvoiceMethod()
    {
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('klarna'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('klarna_account'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('afterpay'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('afterpay_default'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('ratepay'));
        $this->assertEquals(false, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('ideal'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('test_klarna'));
    }

    /**
     * @param string $expectedResult
     * @param string $pspReference
     * @param string $checkoutEnvironment
     * @dataProvider checkoutEnvironmentsProvider
     *
     */
    public function testGetPspReferenceSearchUrl(string $expectedResult, string $pspReference, string $checkoutEnvironment)
    {
        $pspSearchUrl = $this->dataHelper->getPspReferenceSearchUrl($pspReference, $checkoutEnvironment);
        $this->assertEquals($expectedResult, $pspSearchUrl);
    }

    /**
     * Test getHmac method for live mode
     */
    public function testGetHmacForLiveMode()
    {
        $storeId = 1; // Example store ID
        $hmacLive = 'hmac_live_value'; // Example HMAC value for live mode
        $expectedResult = 'decrypted_hmac_live_value'; // Example decrypted HMAC value

        // Mock isDemoMode method to return false
        $this->configHelper->method('isDemoMode')
            ->with($storeId)
            ->willReturn(false);

        // Mock getAdyenHppConfigData method to return HMAC live value
        $this->configHelper->method('getAdyenHppConfigData')
            ->with('hmac_live', $storeId)
            ->willReturn($hmacLive);

        // Mock decrypt method to return decrypted HMAC live value
        $this->encryptor->method('decrypt')
            ->with($hmacLive)
            ->willReturn($expectedResult);

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getHmac($storeId);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function testGetHmacInDemoMode()
    {
        $hmacTest = 'hmac_test_value'; // Example HMAC value for live mode
        $expectedResult = 'decrypted_hmac'; // Example decrypted HMAC value
        $storeId = 1;

        $this->configHelper->method('isDemoMode')
            ->with($storeId)
            ->willReturn(true);

        // Set up the return value for the configHelper
        $this->configHelper->method('getAdyenHppConfigData')
            ->with('hmac_test', $storeId)
            ->willReturn($hmacTest);

        $this->encryptor->method('decrypt')
            ->with($hmacTest)
            ->willReturn($expectedResult);

        // Call the method
        $result = $this->dataHelper->getHmac($storeId);

        // Assertions
        $this->assertEquals('decrypted_hmac', $result);
    }

    /**
     * Test isDemoMode method when demo mode is disabled
     */
    public function testIsDemoModeWhenDisabled()
    {
        $storeId = 1; // Example store ID
        $expectedResult = false; // Example result when demo mode is disabled

        // Mock getAdyenAbstractConfigDataFlag method to return false for demo_mode
        $this->configHelper->method('getAdyenAbstractConfigDataFlag')
            ->with('demo_mode', $storeId)
            ->willReturn($expectedResult);

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->isDemoMode($storeId);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test getAPIKey method in demo mode
     */
    public function testGetAPIKeyInDemoMode()
    {
        $storeId = 1; // Use default store ID for the test
        $expectedResult = 'decrypted_api_key_test_value'; // Expected API key value for demo mode
        $apiKeyTest = 'api_key_test_value';
        $demoMode = true;
        // Mock isDemoMode method to return true
        $this->configHelper->method('isDemoMode')
            ->with($storeId)
            ->willReturn($demoMode);

        // Mock getAdyenAbstractConfigData method to return API key live value
        $this->configHelper->method('getAdyenAbstractConfigData')
            ->with('api_key_test', $storeId)
            ->willReturn($apiKeyTest);

        // Mock decrypt method to return decrypted API key live value
        $this->encryptor->method('decrypt')
            ->with($apiKeyTest)
            ->willReturn($expectedResult);

        // Call the method
        $actualResult = $this->dataHelper->getAPIKey($storeId);

        // Assert that the actual API key matches the expected API key
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test getAPIKey method when not in demo mode
     */
    public function testGetAPIKeyNotInDemoMode()
    {
        $storeId = 1; // Example store ID
        $demoMode = false; // Demo mode is disabled
        $apiKeyLive = 'api_key_live_value'; // Example API key for live mode
        $expectedResult = 'decrypted_api_key_live_value'; // Example decrypted API key

        // Mock isDemoMode method to return false
        $this->configHelper->method('isDemoMode')
            ->with($storeId)
            ->willReturn($demoMode);

        // Mock getAdyenAbstractConfigData method to return API key live value
        $this->configHelper->method('getAdyenAbstractConfigData')
            ->with('api_key_live', $storeId)
            ->willReturn($apiKeyLive);

        // Mock decrypt method to return decrypted API key live value
        $this->encryptor->method('decrypt')
            ->with($apiKeyLive)
            ->willReturn($expectedResult);

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getAPIKey($storeId);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test getWsUsername method in demo mode
     */
    public function testGetWsUsernameInDemoMode()
    {
        $storeId = 1; // Example store ID
        $demoMode = true; // Demo mode is disabled
        $wsUsernameTest = 'ws_username_test_value'; // Example web service username for live mode
        $expectedResult = 'ws_username_test_value'; // Example web service username

        // Mock isDemoMode method to return false
        $this->configHelper->method('isDemoMode')
            ->with($storeId)
            ->willReturn($demoMode);

        // Mock getAdyenAbstractConfigData method to return web service username live value
        $this->configHelper->method('getAdyenAbstractConfigData')
            ->with('ws_username_test', $storeId)
            ->willReturn($wsUsernameTest);

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getWsUsername($storeId);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function testGetWsUsernameNotInDemoMode()
    {
        $storeId = 1; // Example store ID
        $demoMode = false; // Demo mode is disabled
        $wsUsernameLive = 'ws_username_live_value'; // Example web service username for live mode
        $expectedResult = 'ws_username_live_value'; // Example web service username

        // Mock isDemoMode method to return false
        $this->configHelper->method('isDemoMode')
            ->with($storeId)
            ->willReturn($demoMode);

        // Mock getAdyenAbstractConfigData method to return web service username live value
        $this->configHelper->method('getAdyenAbstractConfigData')
            ->with('ws_username_live', $storeId)
            ->willReturn($wsUsernameLive);

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getWsUsername($storeId);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test cancelOrder method
     */
    public function testCancelOrder()
    {
        $orderStatus = 'payment_cancelled'; // Example order status
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock getAdyenAbstractConfigData method to return order status
        $this->configHelper->method('getAdyenAbstractConfigData')
            ->with($orderStatus)
            ->willReturn($orderStatus);

        // Mock canCancel method of the order to return true
        $order->expects($this->once())
            ->method('canCancel')
            ->willReturn(true);

        // Mock cancel method of the order to return the order entity ID
        $order->expects($this->once())
            ->method('getEntityId')
            ->willReturn(1);

        // Call the method
        $this->dataHelper->cancelOrder($order);
    }

    /**
     * Test getMagentoCreditCartType method with known credit card type
     */
    public function testGetMagentoCreditCartTypeWithKnownType()
    {
        $ccType = 'visa'; // Example credit card type
        $expectedResult = 'visa'; // Expected Magento credit card type

        // Call the method
        $actualResult = $this->dataHelper->getMagentoCreditCartType($ccType);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test getMagentoCreditCartType method with unknown credit card type
     */
    public function testGetMagentoCreditCartTypeWithUnknownType()
    {
        $ccType = 'unknown'; // Unknown credit card type
        $expectedResult = 'unknown'; // Expected result is the same as input since mapping not available

        // Call the method
        $actualResult = $this->dataHelper->getMagentoCreditCartType($ccType);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test getCcTypesAltData method
     */
    public function testGetCcTypesAltData()
    {
        // Call the method
        $actualData = $this->dataHelper->getCcTypesAltData();

        // Assert that the actual data matches the expected data
        $this->assertEquals($this->ccTypesAltData, $actualData);
    }

    public function testGetLiveEndpointPrefix()
    {
        $storeId = 1; // Example store ID
        $liveEndpointPrefix = 'live_endpoint_prefix_value'; // Example live endpoint URL prefix
        $expectedResult = 'live_endpoint_prefix_value'; // Example result

        // Mock getAdyenAbstractConfigData method to return live endpoint URL prefix
        $this->configHelper->method('getAdyenAbstractConfigData')
            ->with('live_endpoint_url_prefix', $storeId)
            ->willReturn($liveEndpointPrefix);

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getLiveEndpointPrefix($storeId);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function testGetLiveEndpointPrefixNull()
    {
        $storeId = 1; // Example store ID
        $liveEndpointPrefix = ''; // Example live endpoint URL prefix
        $expectedResult = ''; // Example result

        // Mock getAdyenAbstractConfigData method to return live endpoint URL prefix
        $this->configHelper->method('getAdyenAbstractConfigData')
            ->with('live_endpoint_url_prefix', $storeId)
            ->willReturn($liveEndpointPrefix);

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getLiveEndpointPrefix($storeId);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test getClientKey method when not in demo mode
     */
    public function testGetClientKeyNotInDemoMode()
    {
        $storeId = 1; // Example store ID
        $demoMode = false; // Demo mode is disabled
        $clientKeyLive = 'client_key_live_value'; // Example client key for live mode
        $expectedResult = 'client_key_live_value'; // Example client key

        // Mock isDemoMode method to return false
        $this->configHelper->method('isDemoMode')
            ->with($storeId)
            ->willReturn($demoMode);

        // Mock getAdyenAbstractConfigData method to return client key live value
        $this->configHelper->method('getAdyenAbstractConfigData')
            ->with('client_key_live', $storeId)
            ->willReturn($clientKeyLive);

        // Call the method to get the actual result
        $actualResult = $this->dataHelper->getClientKey($storeId);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test isPaymentMethodOfType method with various scenarios
     *
     * @dataProvider paymentMethodProvider
     */
    public function testIsPaymentMethodOfType($paymentMethod, $type, $expectedResult)
    {
        // Call the method with the provided payment method and type
        $actualResult = $this->dataHelper->isPaymentMethodOfType($paymentMethod, $type);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Test isVatCategoryHigh method with various scenarios
     *
     * @dataProvider paymentMethodProviderForVat
     */
    public function testIsVatCategoryHigh($paymentMethod, $expectedResult)
    {
        // Call the method with the provided payment method
        $actualResult = $this->dataHelper->isVatCategoryHigh($paymentMethod);

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Data provider for isVatCategoryHigh method
     */
    public function paymentMethodProviderForVat(): array
    {
        return [
            ['klarna', true], // KLARNA payment method
            ['afterpay_nl', true], // afterpay_ prefixed payment method
            ['adyen_cc', false], // Non-high VAT category payment method
            ['paypal_express', false], // Non-high VAT category payment method
        ];
    }

    /**
     * Data provider for isPaymentMethodOfType method
     */
    public function paymentMethodProvider(): array
    {
        return [
            ['adyen_cc', 'adyen', true], // Payment method contains the specified type
            ['adyen_sepa', 'adyen', true], // Payment method contains the specified type
            ['paypal_express', 'paypal', true], // Payment method contains the specified type
            ['paypal_express_bml', 'paypal', true], // Payment method contains the specified type
            ['stripe', 'adyen', false], // Payment method does not contain the specified type
            ['paypal_express', 'adyen', false], // Payment method does not contain the specified type
            ['paypal_express_bml', 'stripe', false], // Payment method does not contain the specified type
        ];
    }

    /**
     * Test showLogos method with various scenarios
     *
     * @dataProvider showLogosProvider
     */
    public function testShowLogos($titleRenderer, $expectedResult)
    {
        // Mock ConfigHelper class
        $configHelperMock = $this->getMockBuilder(\Adyen\Payment\Helper\Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Set up ConfigHelper mock to return the specified title_renderer value
        $configHelperMock->method('getAdyenAbstractConfigData')
            ->with('title_renderer')
            ->willReturn($titleRenderer);

        // Call the method
        $actualResult = $this->dataHelper->showLogos();

        // Assert that the actual result matches the expected result
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * Data provider for showLogos method
     */
    public function showLogosProvider(): array
    {
        return [
            [RenderMode::MODE_TITLE, false], // Title renderer mode is text, logos should not be shown
            [RenderMode::MODE_TITLE_IMAGE, false], // Title renderer mode is image, logos should be shown
            ['other_mode', false], // Invalid mode, logos should not be shown
        ];
    }

    public static function checkoutEnvironmentsProvider(): array
    {
        return [
            [
                'https://ca-test.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=7914073381342284',
                '7914073381342284',
                'false'
            ],
            [
                'https://ca-live.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=883580976999434D',
                '883580976999434D',
                'true'
            ]
        ];
    }

    /*
     * Provider for testInitializeAdyenClientWithClientConfig test case
     */
    public static function clientConfigProvider(): array
    {
        return [
            [
                '$clientConfig' => [
                    'storeId' => 1
                ]
            ],
            [
                '$clientConfig' => [
                    'storeId' => 1,
                    'isMotoTransaction' => true,
                    'motoMerchantAccount' => 'TESTMERCHANTACCOUNT'
                ]
            ]
        ];
    }

    /**
     * @dataProvider clientConfigProvider
     */
    public function testInitializeAdyenClientWithClientConfig($clientConfig)
    {
        $this->assertInstanceOf(
            Client::class,
            $this->dataHelper->initializeAdyenClientWithClientConfig($clientConfig)
        );
    }

    public function testGetMagentoDetails()
    {
        $expectedDetails = [
            'name' => 'magento',
            'version' => '2.x.x',
            'edition' => 'Community'
        ];

        $actualDetails = $this->dataHelper->getMagentoDetails();
        $this->assertEquals($expectedDetails, $actualDetails);
    }

    public function testBuildRequestHeaders()
    {
        $expectedHeaders = [
            'external-platform-name' => 'magento',
            'external-platform-version' => '2.x.x',
            'external-platform-edition' => 'Community',
            'merchant-application-name' => 'adyen-magento2',
            'merchant-application-version' => '1.2.3'
        ];

        $headers = $this->dataHelper->buildRequestHeaders();
        $this->assertEquals($expectedHeaders, $headers);
    }

    public function testLogResponse()
    {
        // Set up dummy data
        $response = ['key' => 'value'];
        $storeId = 1; // Example store ID
        $isDemo = true; // Example demo mode flag

        // Mock methods for store manager and config helper
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn($storeId);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->configHelper->method('isDemoMode')->with($storeId)->willReturn($isDemo);

        // Assert that the logger was called with the correct parameters based on demo mode
        $this->adyenLogger->expects($this->once())
            ->method('info')
            ->with('Response from Adyen API', ['body' => $response]);
        // Call the method under test
        $this->dataHelper->logResponse($response);

    }

    public function testLogResponseWhenDemoModeFalse()
    {
        // Set up dummy data
        $storeId = 1; // Example store ID
        $isDemo = false; // Demo mode is false

        // Mock methods for store manager and config helper
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn($storeId);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->configHelper->method('isDemoMode')->with($storeId)->willReturn($isDemo);

        // Expect filtering of references in the response
        $filteredResponse = ['reference' => '123'];
        // Mock the private method filterReferences using ReflectionClass
        $reflectionClass = new ReflectionClass(Data::class);
        $method = $reflectionClass->getMethod('filterReferences');
        $method->setAccessible(true);
        $filteredResponse = $method->invokeArgs($this->dataHelper, [$filteredResponse]);

        $this->adyenLogger->expects($this->once())
            ->method('info')
            ->with('Response from Adyen API', ['body' => $filteredResponse]);
        // Call the method under test
        $this->dataHelper->logResponse($filteredResponse);
    }

    public function testLogRequest()
    {
        // Set up dummy data
        $request = ['reference' => '123', 'not_reference' => '456'];
        $apiVersion = 'v1';
        $endpoint = 'payment/authorise';

        // Set up store ID and demo mode
        $storeId = 1; // Example store ID
        $isDemo = false; // Demo mode is false

        // Mock methods for store manager and config helper
        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn($storeId);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->configHelper->method('isDemoMode')->with($storeId)->willReturn($isDemo);

        $this->configHelper->method('getLiveEndpointPrefix')->willReturn('live_prefix');
        $reflectionClass = new ReflectionClass(Data::class);
        $method = $reflectionClass->getMethod('filterReferences');
        $method->setAccessible(true);

        $filteredResponse = $method->invokeArgs($this->dataHelper, [$request]);

        // Expect info method to be called on adyenLogger with correct context
        $this->adyenLogger->expects($this->once())
            ->method('info')
            ->with('Request to Adyen API payment/authorise', [
                'apiVersion' => $apiVersion,
                'livePrefix' => 'live_prefix',
                'body' => $filteredResponse
            ]);

        // Call the method under test
        $this->dataHelper->logRequest($request, $apiVersion, $endpoint);
    }

    public function testPadShopperReference()
    {
        // Test case 1: When the shopper reference length is less than 3
        $shopperReference1 = '12';
        $expected1 = '012'; // Expected output after padding
        $result1 = $this->dataHelper->padShopperReference($shopperReference1);
        $this->assertEquals($expected1, $result1);

        // Test case 2: When the shopper reference length is equal to 3
        $shopperReference2 = '123';
        $expected2 = '123'; // No padding needed
        $result2 = $this->dataHelper->padShopperReference($shopperReference2);
        $this->assertEquals($expected2, $result2);

        // Test case 3: When the shopper reference length is greater than 3
        $shopperReference3 = '1234';
        $expected3 = '1234'; // No padding needed
        $result3 = $this->dataHelper->padShopperReference($shopperReference3);
        $this->assertEquals($expected3, $result3);
    }

    public function testGetCurrentLocaleCode()
    {
        // Mock dependencies
        $storeId = 1;

        // Configure mocks
        $this->configHelper->method('getAdyenHppConfigData')
            ->willReturnMap([
                ['shopper_locale', $storeId, 'en_US'], // Mocking a shopper locale value
            ]);
        $this->localeHelper->method('mapLocaleCode')
            ->willReturnMap([
                ['en_US', 'en_US'], // Mocking locale mapping
            ]);
        $this->localeResolver->method('getLocale')
            ->willReturn('en_US'); // Mocking locale resolver
        $this->config->method('getValue')
            ->willReturn('en_US'); // Mocking default locale config

        // Test case: When a shopper locale is provided
        $expected1 = 'en_US';
        $result1 = $this->dataHelper->getCurrentLocaleCode($storeId);
        $this->assertEquals($expected1, $result1);

        // Test case: When neither shopper locale nor locale resolver returns a value
        $this->localeResolver->method('getLocale')
            ->willReturn('');
        $expected3 = 'en_US'; // Assuming en_US is the default locale
        $result3 = $this->dataHelper->getCurrentLocaleCode($storeId);
        $this->assertEquals($expected3, $result3);
    }

    public function testBuildThreeDS2ProcessResponseJson()
    {
        // Test case: When type and token are not provided
        $expected1 = '{"threeDS2":false}';
        $result1 = $this->dataHelper->buildThreeDS2ProcessResponseJson();
        $this->assertEquals($expected1, $result1);

        // Test case: When type is provided but token is not provided
        $expected2 = '{"threeDS2":false,"type":"example"}';
        $result2 = $this->dataHelper->buildThreeDS2ProcessResponseJson('example');
        $this->assertEquals($expected2, $result2);

        // Test case: When both type and token are provided
        $expected3 = '{"threeDS2":true,"type":"example","token":"xyz123"}';
        $result3 = $this->dataHelper->buildThreeDS2ProcessResponseJson('example', 'xyz123');
        $this->assertEquals($expected3, $result3);
    }
}

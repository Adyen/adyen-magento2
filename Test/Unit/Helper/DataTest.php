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

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Config as AdyenConfig;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\RecurringType;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory as NotificationCollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout\ModificationsApi;
use Adyen\Service\Checkout\OrdersApi;
use Adyen\Service\Checkout\PaymentsApi;
use Adyen\Service\RecurringApi;
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
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;

class DataTest extends AbstractAdyenTestCase
{
    /**
     * @var Data
     */
    private $dataHelper;

    private Client $clientMock;

    private AdyenLogger $adyenLoggerMock;

    public function setUp(): void
    {
        $this->clientMock = $this->createConfiguredMock(Client::class, [
                'getConfig' => new AdyenConfig(['environment' => 'test'])
            ]
        );
        $configHelper = $this->createConfiguredMock(ConfigHelper::class, [
            'getMotoMerchantAccountProperties' => [
                'apikey' => 'wellProtectedEncryptedApiKey',
                'demo_mode' => '1'
            ],
            'getAdyenAbstractConfigDataFlag' => '1',
            'getAdyenHppConfigData' => 'hmac'
        ]);
        $configHelper->method('getAdyenAbstractConfigData')
            ->willReturnCallback(function ($config) {
                return $config . '_1';
            });
        $context = $this->createMock(Context::class);
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')
            ->willReturnCallback(function ($data) {
                return $data;
            });
        $dataStorage = $this->createMock(DataInterface::class);
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
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $storeManager = $this->createConfiguredMock(StoreManager::class, [
            'getStore' => $this->createConfiguredMock(Store::class, [
                'getId' => 1
            ])
        ]);

        $cache = $this->createMock(CacheInterface::class);
        $localeResolver = $this->createMock(ResolverInterface::class);
        $config = $this->createMock(ScopeConfigInterface::class);
        $componentRegistrar = $this->createConfiguredMock(ComponentRegistrarInterface::class, [
            'getPath' => 'vendor/adyen/module-payment'
        ]);
        $localeHelper = $this->createMock(Locale::class);
        $orderManagement = $this->createMock(OrderManagementInterface::class);
        $orderStatusHistoryFactory = $this->createGeneratedMock(HistoryFactory::class);

        // Partial mock builder is being used for mocking the methods in the class being tested.
        $this->dataHelper = $this->getMockBuilder(Data::class)
            ->setMethods(['getModuleVersion'])
            ->setConstructorArgs([
                $context,
                $encryptor,
                $dataStorage,
                $country,
                $moduleList,
                $assetRepo,
                $assetSource,
                $notificationFactory,
                $taxConfig,
                $taxCalculation,
                $backendHelper,
                $productMetadata,
                $this->adyenLoggerMock,
                $storeManager,
                $cache,
                $localeResolver,
                $config,
                $componentRegistrar,
                $localeHelper,
                $orderManagement,
                $orderStatusHistoryFactory,
                $configHelper
            ])
            ->getMock();

        $this->dataHelper->expects($this->any())
            ->method('getModuleVersion')
            ->willReturn('1.2.3');
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
            'external-platform-name' => 'adyen-magento2',
            'external-platform-version' => '1.2.3',
            'merchant-application-name' => 'magento',
            'merchant-application-version' => '2.x.x',
            'merchant-application-edition' => 'Community'
        ];

        $headers = $this->dataHelper->buildRequestHeaders();
        $this->assertEquals($expectedHeaders, $headers);
    }

    /**
     * @test
     */
    public function getRecurringTypesShouldReturnAnArrayOfRecurringTypes()
    {
        $this->assertEquals([
            RecurringType::ONECLICK => 'ONECLICK',
            RecurringType::ONECLICK_RECURRING => 'ONECLICK,RECURRING',
            RecurringType::RECURRING => 'RECURRING'
        ], $this->dataHelper->getRecurringTypes());
    }

    public function getCheckoutFrontendRegionsShouldReturnAnArray()
    {
        $this->assertEquals([
            'eu' => 'Default (EU - Europe)',
            'au' => 'AU - Australasia',
            'us' => 'US - United States',
            'in' => 'IN - India'
        ], $this->dataHelper->getRecurringTypes());
    }

    public function testGetClientKey()
    {
        $key = $this->dataHelper->getClientKey(1);
        $this->assertEquals('client_key_test_1',$key);
    }

    public function testGetApiKey()
    {
        $key = $this->dataHelper->getAPIKey(1);
        $this->assertEquals('api_key_test_1',$key);
    }

    public function testIsDemoMode()
    {
        $this->assertEquals('1', $this->dataHelper->isDemoMode(1));
    }

    public function testGetHmac()
    {
        $hmac = $this->dataHelper->getHmac(1);
        $this->assertEquals('hmac', $hmac);
    }

    public function testGetCheckoutFrontendRegions()
    {
        $regions = $this->dataHelper->getCheckoutFrontendRegions();
        $expected =  [
            'eu' => 'Default (EU - Europe)',
            'au' => 'AU - Australasia',
            'us' => 'US - United States',
            'in' => 'IN - India'
        ];
        $this->assertSame($expected, $regions);
    }

    public function testCaptureModes()
    {
        $this->assertSame(
            [
                'auto' => 'Immediate',
                'manual' => 'Manual'
            ],
            $this->dataHelper->getCaptureModes()
        );
    }

    public function testGetOpenInvoiceCaptureModes()
    {
        $this->assertSame(
            [
                'auto' => 'Immediate',
                'manual' => 'Manual',
                'onshipment' => 'On shipment'
            ],
            $this->dataHelper->getOpenInvoiceCaptureModes()
        );
    }

    public function testGetPaymentRoutines()
    {
        $this->assertSame(
            [
                'single' => 'Single Page Payment Routine',
                'multi' => 'Multi-page Payment Routine'
            ],
            $this->dataHelper->getPaymentRoutines()
        );
    }

    public function testGetMinorUnitTaxPercent()
    {
        $this->assertSame(
            1000,
            $this->dataHelper->getMinorUnitTaxPercent(10)
        );
    }

    public function testInitializePaymentsApi()
    {
        $service = $this->dataHelper->initializePaymentsApi($this->clientMock);
        $this->assertInstanceOf(PaymentsApi::class, $service);
    }

    public function testInitializeModificationsApi()
    {
        $service = $this->dataHelper->initializeModificationsApi($this->clientMock);
        $this->assertInstanceOf(ModificationsApi::class, $service);
    }

    public function testInitializeRecurringApi()
    {
        $service = $this->dataHelper->initializeRecurringApi($this->clientMock);
        $this->assertInstanceOf(RecurringApi::class, $service);
    }

    public function testInitializeOrdersApi()
    {
        $service = $this->dataHelper->initializeOrdersApi($this->clientMock);
        $this->assertInstanceOf(OrdersApi::class, $service);
    }

    public function testLogAdyenException()
    {
        $this->adyenLoggerMock->expects($this->once())->method('info');
        $this->dataHelper->logAdyenException(new AdyenException('error message', 123));
    }
}

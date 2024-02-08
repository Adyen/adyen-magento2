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

class DataTest extends AbstractAdyenTestCase
{
    /**
     * @var Data
     */
    private $dataHelper;

    public function setUp(): void
    {
        $configHelper = $this->createConfiguredMock(ConfigHelper::class, [
            'getMotoMerchantAccountProperties' => [
                'apikey' => 'wellProtectedEncryptedApiKey',
                'demo_mode' => '1'
            ]
        ]);
        $context = $this->createMock(Context::class);
        $encryptor = $this->createMock(EncryptorInterface::class);
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
        $adyenLogger = $this->createMock(AdyenLogger::class);
        $storeManager = $this->createMock(StoreManager::class);
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
                $adyenLogger,
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
            'external-platform-name' => 'magento',
            'external-platform-version' => '2.4.6',
            'external-platform-edition' => 'Community',
            'merchant-application-name' => 'adyen-magento2',
            'merchant-application-version' => '9.0.5'
        ];

        $headers = $this->dataHelper->buildRequestHeaders();
        $this->assertEquals($expectedHeaders, $headers);
    }
}

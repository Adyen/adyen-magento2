<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data as AdyenDataHelper;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Util\ManualCapture;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Payment\Helper\Data as MagentoDataHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use ReflectionClass;

class PaymentMethodsTest extends AbstractAdyenTestCase
{
    private PaymentMethods $paymentMethodsHelper;
    private Context $contextMock;
    private CartRepositoryInterface $quoteRepositoryMock;
    private ScopeConfigInterface $configMock;
    private Data $adyenHelperMock;
    private ResolverInterface $localeResolverMock;
    private AdyenLogger $adyenLoggerMock;
    private Repository $assetRepoMock;
    private RequestInterface $requestMock;
    private Source $assetSourceMock;
    private DesignInterface $designMock;
    private ThemeProviderInterface $themeProviderMock;
    private ChargedCurrency $chargedCurrencyMock;
    private Config $configHelperMock;
    private MagentoDataHelper $dataHelperMock;
    private ManualCapture $manualCaptureMock;
    private SerializerInterface $serializerMock;
    private AdyenDataHelper $adyenDataHelperMock;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        //$this->configMock = $this->createMock(ScopeConfigInterface::class);
        $this->configMock = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->localeResolverMock = $this->createMock(ResolverInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->assetRepoMock = $this->createMock(Repository::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->assetSourceMock = $this->createMock(Source::class);
        $this->designMock = $this->createMock(DesignInterface::class);
        $this->themeProviderMock = $this->createMock(ThemeProviderInterface::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->dataHelperMock = $this->createMock(MagentoDataHelper::class);
        $this->manualCaptureMock = $this->createMock(ManualCapture::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->adyenDataHelperMock = $this->createMock(AdyenDataHelper::class);
        $this->storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Instantiate the PaymentMethods helper class with the mocked dependencies
        $this->paymentMethodsHelper = new PaymentMethods(
            $this->contextMock,
            $this->quoteRepositoryMock,
            $this->configMock,
            $this->adyenHelperMock,
            $this->localeResolverMock,
            $this->adyenLoggerMock,
            $this->assetRepoMock,
            $this->requestMock,
            $this->assetSourceMock,
            $this->designMock,
            $this->themeProviderMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $this->dataHelperMock,
            $this->manualCaptureMock,
            $this->serializerMock,
            $this->adyenDataHelperMock,
        );
    }

    /**
     * @dataProvider comparePaymentMethodProvider
     */
    public function testCompareOrderAndWebhookPaymentMethods(
        $orderPaymentMethod,
        $notificationPaymentMethod,
        $assert,
        $ccType = null
    )
    {
        $objectManager = new ObjectManager($this);
        $paymentMethodsHelper = $objectManager->getObject(PaymentMethods::class, []);
        $methodMock = $this->createMock(MethodInterface::class);
        $methodMock->method('getConfigData')
            ->willReturnMap([
                ['group', null, PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS],
                ['is_wallet', null, '0']
            ]);
        $methodMock->method('getCode')->willReturn($orderPaymentMethod);

        $paymentMock = $this->createMock(Order\Payment::class);
        $paymentMock->method('getMethodInstance')->willReturn($methodMock);
        $paymentMock->method('getMethod')->willReturn($orderPaymentMethod);
        $paymentMock->method('getCcType')->willReturn($ccType);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $notificationMock = $this->createMock(Notification::class);
        $notificationMock->method('getPaymentMethod')->willReturn($notificationPaymentMethod);

        $this->assertEquals(
            $assert,
            $paymentMethodsHelper->compareOrderAndWebhookPaymentMethods($orderMock, $notificationMock)
        );
    }

    public static function comparePaymentMethodProvider(): array
    {
        return [
            [
                'orderPaymentMethod' => 'adyen_klarna',
                'notificationPaymentMethod' => 'klarna',
                'assert' => true
            ],
            [
                'orderPaymentMethod' => 'adyen_cc',
                'notificationPaymentMethod' => 'visa',
                'assert' => true,
                'ccType' => 'visa'
            ],
            [
                'orderPaymentMethod' => 'adyen_klarna',
                'notificationPaymentMethod' => 'boleto',
                'assert' => false
            ]
        ];
    }

    public function testTogglePaymentMethodsActivation()
    {
        $this->configHelperMock
            ->expects($this->once())
            ->method('getIsPaymentMethodsActive')
            ->willReturn(true);
        $this->dataHelperMock
            ->expects($this->once())
            ->method('getPaymentMethodList')
            ->willReturn(
                [
                    'adyen_cc' => [],
                    'adyen_oneclick' => [],
                    'adyen_cc_vault' => []
                ]);

        $this->configHelperMock
            ->expects($this->exactly(3))
            ->method('setConfigData')
            ->withConsecutive(
                ['1', 'active', 'adyen_cc', 'default'],
                ['1', 'active', 'adyen_oneclick', 'default'],
                ['1', 'active', 'adyen_cc_vault', 'default']
            );

        $paymentMethods = $this->paymentMethodsHelper->togglePaymentMethodsActivation();
        $this->assertSame(
            ['adyen_cc', 'adyen_oneclick', 'adyen_cc_vault'],
            $paymentMethods
        );
    }

    //Successfully retrieve payment methods for a valid quote ID.
    public function testSuccessfullyRetrievePaymentMethodsForValidQuoteId()
    {
        $quoteId = 123; // Example valid quote ID
        $country = 'US'; // Example country
        $shopperLocale = 'en_US'; // Example shopper locale
        $storeId = 1;

        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create a mock for the Store object
        $storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();

        $storeMock->expects($this->any())
            ->method('getId')
            ->willReturn($storeId);

        // Mock the getId method of the quote to return the quoteId
        $quoteMock->expects($this->any())
            ->method('getStore')
            ->willReturn($storeMock);

        // Mock the quote repository to return the quote mock
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')
            ->with($quoteId)
            ->willReturn($quoteMock);

        // Perform the test
        $result = $this->paymentMethodsHelper->getPaymentMethods($quoteId, $country, $shopperLocale);

        $this->assertNotEmpty($result);
    }

    public function testRetrievePaymentMethodsWithInvalidQuoteId()
    {
        $invalidQuoteId = 999; // Example invalid quote ID
        $country = 'US'; // Example country
        $shopperLocale = 'en_US'; // Example shopper locale

        // Mock the quote repository to return null for an invalid quote ID
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')
            ->with($invalidQuoteId)
            ->willReturn(null);

        // Perform the test
        $result = $this->paymentMethodsHelper->getPaymentMethods($invalidQuoteId, $country, $shopperLocale);

        // Assert that the result is an array
        $this->assertEmpty($result);
    }

    public function testGetAdyenPaymentMethods()
    {
        // Mock the Data helper class
        $dataHelperMock = $this->getMockBuilder(MagentoDataHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Define the list of payment methods to be returned by the mock
        $paymentMethods = [
            'adyen_cc' => [],
            'adyen_oneclick' => [],
            'paypal' => [], // Non-Adyen payment method
            'adyen_sepa' => [],
        ];

        // Set up the expected filtered Adyen payment methods
        $expectedAdyenPaymentMethods = [
            'adyen_cc',
            'adyen_oneclick',
            'adyen_sepa',
        ];

        // Set up the mock to return the predefined list of payment methods
        $dataHelperMock->expects($this->once())
            ->method('getPaymentMethodList')
            ->willReturn($paymentMethods);

        $paymentMethods = new PaymentMethods(
            $this->contextMock,
            $this->quoteRepositoryMock,
            $this->configMock,
            $this->adyenHelperMock,
            $this->localeResolverMock,
            $this->adyenLoggerMock,
            $this->assetRepoMock,
            $this->requestMock,
            $this->assetSourceMock,
            $this->designMock,
            $this->themeProviderMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $dataHelperMock,
            $this->manualCaptureMock,
            $this->serializerMock,
            $this->adyenDataHelperMock
            );
        // Call the getAdyenPaymentMethods() method
        $actualAdyenPaymentMethods = $paymentMethods->getAdyenPaymentMethods();

        // Assert that the returned array contains only the expected Adyen payment methods
        $this->assertEquals($expectedAdyenPaymentMethods, $actualAdyenPaymentMethods);
    }

    public function testIsAdyenPayment()
    {
        // Define the list of Adyen payment methods

        $adyenPaymentMethods = [
            'adyen_cc' => [],
            'adyen_oneclick' => [],
            'paypal' => [], // Non-Adyen payment method
            'adyen_sepa' => [],
        ];

        // Mock the Data helper class
        $dataHelperMock = $this->getMockBuilder(MagentoDataHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Set up the mock to return the predefined list of Adyen payment methods
        $dataHelperMock->expects($this->exactly(2))
            ->method('getPaymentMethodList')
            ->willReturn($adyenPaymentMethods);

        // Instantiate the PaymentMethods class with the mocked Data helper
        $paymentMethods = new PaymentMethods(
            $this->contextMock,
            $this->quoteRepositoryMock,
            $this->configMock,
            $this->adyenHelperMock,
            $this->localeResolverMock,
            $this->adyenLoggerMock,
            $this->assetRepoMock,
            $this->requestMock,
            $this->assetSourceMock,
            $this->designMock,
            $this->themeProviderMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $dataHelperMock,
            $this->manualCaptureMock,
            $this->serializerMock,
            $this->adyenDataHelperMock
        );

        // Test for an Adyen payment method code
        $this->assertTrue($paymentMethods->isAdyenPayment('adyen_cc'));

        // Test for a non-Adyen payment method code
        $this->assertFalse($paymentMethods->isAdyenPayment('paypal'));
    }

    public function testFetchPaymentMethodsWhenMerchantAccountEmpty()
    {
        $this->objectManager = new ObjectManager($this);
        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelperMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentMethodsHelper = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock,
                'configHelper' => $this->configHelperMock,
            ]
        );
        $this->configHelperMock->expects($this->once())
            ->method('getAdyenAbstractConfigData')
            ->willReturn(null);

        $jsonResponse = $paymentMethodsHelper->fetchPaymentMethods();
        $this->assertEquals(json_encode([]), $jsonResponse);
    }

    public function testFetchPaymentMethods()
    {
        $country = 'US';
        $shopperLocale = 'en_US';
        $objectManager = new ObjectManager($this);
        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentMethodsHelper = $objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock,
                'configHelper' => $this->configHelperMock,
            ]
        );

        $storeId = 1;

        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create a mock for the Store object
        $storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();

        $storeMock->expects($this->any())
            ->method('getId')
            ->willReturn($storeId);

        // Mock the getId method of the quote to return the quoteId
        $quoteMock->expects($this->any())
            ->method('getStore')
            ->willReturn($storeMock);

        // Use ReflectionClass to access the protected method
        $reflectionClass = new ReflectionClass(PaymentMethods::class);
        $fetchPaymentMethodsMethod = $reflectionClass->getMethod('fetchPaymentMethods');
        $fetchPaymentMethodsMethod->setAccessible(true);

        // Call the protected method with the necessary parameters
        $result = $fetchPaymentMethodsMethod->invoke($paymentMethodsHelper, $country, $shopperLocale);

        // Assert the result
        $this->assertIsString($result); // Ensure the result is a string

        // Convert the JSON string to an array for further assertions
        $resultArray = json_decode($result, true);

        // Assert the structure of the response array
        $this->assertArrayHasKey('paymentMethodsResponse', $resultArray);
        $this->assertArrayHasKey('paymentMethodsExtraDetails', $resultArray);

        // Assert the content of the paymentMethodsResponse
        $paymentMethodsResponse = $resultArray['paymentMethodsResponse'];
        $this->assertArrayHasKey('paymentMethods', $paymentMethodsResponse);
        $this->assertIsArray($paymentMethodsResponse['paymentMethods']);

        // Assert the content of the paymentMethodsExtraDetails
        $paymentMethodsExtraDetails = $resultArray['paymentMethodsExtraDetails'];
        $this->assertIsArray($paymentMethodsExtraDetails);

        // Add more specific assertions as needed based on the expected structure and content
    }
}

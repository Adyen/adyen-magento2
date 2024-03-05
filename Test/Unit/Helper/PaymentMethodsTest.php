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

use Adyen\Client;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data as AdyenDataHelper;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout;
use Adyen\Util\ManualCapture;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Payment\Helper\Data as MagentoDataHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use ReflectionClass;
use Adyen\AdyenException;
use Exception;
use ReflectionMethod;

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
        $this->configMock = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->billingAddressMock = $this->getMockBuilder(Address::class)
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
        $this->amountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $this->methodMock = $this->createMock(MethodInterface::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->notificationMock = $this->createMock(Notification::class);
        $this->orderPaymentMock = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->addMethods(['getCustomerId'])
            ->onlyMethods(['getStore','getBillingAddress','getEntityId'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectManager = new ObjectManager($this);

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
        $paymentMethodsHelper = $this->objectManager->getObject(PaymentMethods::class, []);
        $this->methodMock->method('getConfigData')
            ->willReturnMap([
                ['group', null, PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS],
                ['is_wallet', null, '0']
            ]);
        $this->methodMock->method('getCode')->willReturn($orderPaymentMethod);

        $this->orderPaymentMock->method('getMethodInstance')->willReturn($this->methodMock);
        $this->orderPaymentMock->method('getMethod')->willReturn($orderPaymentMethod);
        $this->orderPaymentMock->method('getCcType')->willReturn($ccType);
        $this->orderMock->method('getPayment')->willReturn($this->orderPaymentMock);
        $this->notificationMock->method('getPaymentMethod')->willReturn($notificationPaymentMethod);

        $this->assertEquals(
            $assert,
            $paymentMethodsHelper->compareOrderAndWebhookPaymentMethods($this->orderMock, $this->notificationMock)
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

    //Successfully retrieve payment methods for a valid quote ID. getPaymentMethods
    public function testSuccessfullyRetrievePaymentMethodsForValidQuoteId()
    {
        $quoteId = 123; // Example valid quote ID
        $country = 'US'; // Example country
        $shopperLocale = 'en_US'; // Example shopper locale
        $storeId = 1;

        $this->storeMock->expects($this->any())
            ->method('getId')
            ->willReturn($storeId);

        // Mock the getId method of the quote to return the quoteId
        $this->quoteMock->expects($this->any())
            ->method('getStore')
            ->willReturn($this->storeMock);

        // Mock the quote repository to return the quote mock
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')
            ->with($quoteId)
            ->willReturn($this->quoteMock);

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

        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'dataHelper' => $dataHelperMock,
            ]
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

        // Set up the mock to return the predefined list of Adyen payment methods
        $this->dataHelperMock->expects($this->exactly(2))
            ->method('getPaymentMethodList')
            ->willReturn($adyenPaymentMethods);

        // Test for an Adyen payment method code
        $this->assertTrue($this->paymentMethodsHelper->isAdyenPayment('adyen_cc'));

        // Test for a non-Adyen payment method code
        $this->assertFalse($this->paymentMethodsHelper->isAdyenPayment('paypal'));
    }

    /**
     * @throws ReflectionExceptionAlias
     */
    private function getPrivateMethod(string $className, string $methodName): ReflectionMethod
    {
        $reflectionClass = new ReflectionClass($className);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    public function testFetchPaymentMethodsWhenMerchantAccountEmpty()
    {
        $country = 'US';
        $shopperLocale = 'en_US';

        // Invoke the private method
        $fetchPaymentMethodsMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'fetchPaymentMethods'
        );

        $storeId = 1;

        $this->storeMock->expects($this->any())
            ->method('getId')
            ->willReturn($storeId);

        // Mock the getId method of the quote to return the quoteId
        $this->quoteMock->expects($this->any())
            ->method('getStore')
            ->willReturn($this->storeMock);

        $paymentMethodsHelper = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock,
                'configHelper' => $this->configHelperMock,
            ]
        );

        // Call the protected method with the necessary parameters
        $result = $fetchPaymentMethodsMethod->invoke($paymentMethodsHelper, $country, $shopperLocale);

        // Assert the result
        $this->assertIsString($result); // Ensure the result is a string

        $this->assertEquals(json_encode([]), $result);
    }

    public function testFetchPaymentMethodsWithEmptyResponseFromAdyenApi()
    {
        $quoteId = 1;
        $storeId = 1;
        $amountValue = 100;
        $adyenClientMock = $this->createMock(Client::class);
        $checkoutServiceMock = $this->createMock(Checkout::class);
        // Setup test scenario
        $this->storeMock->expects($this->any())
            ->method('getId')
            ->willReturn($quoteId);

        // Mock the getId method of the quote to return the quoteId
        $this->quoteMock->expects($this->any())
            ->method('getStore')
            ->willReturn($this->storeMock);
        $this->configHelperMock->expects($this->once())
            ->method('getAdyenAbstractConfigData')
            ->with('merchant_account', $storeId) // Ensure it's called with the expected parameters
            ->willReturn('mocked_merchant_account'); // Define the return value for the mocked method
        $this->adyenHelperMock->method('initializeAdyenClient')->willReturn($adyenClientMock);
        $this->adyenHelperMock->method('createAdyenCheckoutService')->willReturn($checkoutServiceMock);
        $this->amountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');
        $this->amountCurrencyMock->method('getAmount')->willReturn($amountValue);
        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($this->amountCurrencyMock);
        $this->billingAddressMock->expects($this->once())
            ->method('getCountryId')
            ->willReturn('NL');
        $this->quoteMock
            ->method('getBillingAddress')
            ->willReturn($this->billingAddressMock);
        // Simulate successful API call
        $checkoutServiceMock->expects($this->once())
            ->method('paymentMethods')
            ->willThrowException(new AdyenException("The Payment methods response is empty check your Adyen configuration in Magento."));

        $fetchPaymentMethodsMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'fetchPaymentMethods'
        );

        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock,
                'configHelper' => $this->configHelperMock,
                'chargedCurrency' => $this->chargedCurrencyMock,
                'adyenHelper' => $this->adyenHelperMock
            ]
        );

        // Execute method of the tested class
        $result = $fetchPaymentMethodsMethod->invoke($paymentMethods, null, null);

        // Assert conditions
        $this->assertEquals(json_encode([]), $result);
    }

    public function testSuccessfulRetrievalOfPaymentMethods()
    {
        $expectedResult = [
            'paymentMethods' => [
                '0' => [
                'type' => 'method1'
                ],
                '1' => [
                    'type' => 'method1'
                ]
            ]
        ];

        $adyenClientMock = $this->createMock(Client::class);
        $checkoutServiceMock = $this->createMock(Checkout::class);
        $quoteId = 1;
        $storeId = 1;
        $amountValue = '100';

        $requestParams = [
            "channel" => "Web",
            "merchantAccount" => 'MagentoMerchantTest',
            "shopperReference" => 'SomeShopperRef',
            "countryCode" => 'NL',
            "shopperLocale" => 'nl-NL',
            "amount" => [
                "currency" => 'EUR',
                "value" => $amountValue
            ]
        ];

        $paymentMethodsExtraDetails['type']['configuration'] = [
            'amount' => [
                'value' => $amountValue,
                'currency' => 'EUR'
            ],
            'currency' => 'EUR',
        ];

        // Create a partial mock for your class
        $paymentMethodsMock = $this->getMockBuilder(PaymentMethods::class)
            ->onlyMethods(['getPaymentMethodsRequest', 'addExtraConfigurationToPaymentMethods']) // Specify the method(s) to mock
            ->disableOriginalConstructor()
            ->getMock();

        // Set up the expectation for the mocked method
        $paymentMethodsMock->expects($this->any())
            ->method('getPaymentMethodsRequest')
            ->willReturn($requestParams);

        $paymentMethodsMock->expects($this->any())
            ->method('addExtraConfigurationToPaymentMethods')
            ->willReturn($paymentMethodsExtraDetails);

        $this->adyenHelperMock->method('initializeAdyenClient')->willReturn($adyenClientMock);

        $this->adyenHelperMock->method('createAdyenCheckoutService')->willReturn($checkoutServiceMock);

        // Simulate successful API call
        $checkoutServiceMock->expects($this->once())
            ->method('paymentMethods')
            ->willReturn($expectedResult);

        $this->storeMock->expects($this->any())
            ->method('getId')
            ->willReturn($quoteId);

        // Mock the getId method of the quote to return the quoteId
        $this->quoteMock->expects($this->any())
            ->method('getStore')
            ->willReturn($this->storeMock);

        $this->configHelperMock->expects($this->once())
            ->method('getAdyenAbstractConfigData')
            ->with('merchant_account', $storeId) // Ensure it's called with the expected parameters
            ->willReturn('mocked_merchant_account'); // Define the return value for the mocked method


        $this->amountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');
        $this->amountCurrencyMock->method('getAmount')->willReturn($amountValue);
        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($this->amountCurrencyMock);

        $this->adyenHelperMock->expects($this->once())
            ->method('logResponse')
            ->with($expectedResult);

        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock,
                'configHelper' => $this->configHelperMock,
                'chargedCurrency' => $this->chargedCurrencyMock,
                'adyenHelper' => $this->adyenHelperMock,
                'paymentMethods' => $paymentMethodsMock,
            ]
        );
        $fetchPaymentMethodsMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'fetchPaymentMethods'
        );
        $result = $fetchPaymentMethodsMethod->invoke($paymentMethods, 'NL', 'nl_NL');

        $this->assertJson($result);
    }

    public function testGetCurrentCountryCodeWithBillingAddressSet()
    {
        $this->billingAddressMock->expects($this->once())
            ->method('getCountryId')
            ->willReturn('NL'); // Simulate the billing address country is set to US
        $this->quoteMock->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($this->billingAddressMock);

        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock
            ]
        );

        $getCurrentCountryCodeMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'getCurrentCountryCode'
        );

        $result = $getCurrentCountryCodeMethod->invoke($paymentMethods, $this->storeMock);

        // Assert that the expected country code is returned
        $this->assertEquals('NL', $result);
    }

    public function testGetCurrentCountryCodeWithNoBillingAddressSet()
    {
        $storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockNoCountry = null;

        // Set up expectations for the mocked objects
        $billingAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $billingAddressMock->expects($this->once())
            ->method('getCountryId')
            ->willReturn($mockNoCountry);
        $this->quoteMock->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($billingAddressMock);

        $this->configMock->expects($this->any())
            ->method('getValue')
            ->willReturn('GB');

        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock,
                'config' => $this->configMock,
            ]
        );

        $getCurrentCountryCodeMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'getCurrentCountryCode'
        );

        $result = $getCurrentCountryCodeMethod->invoke($paymentMethods, $storeMock);

        // Assert that the expected country code is returned
        $this->assertEquals('GB', $result);
    }

    public function testGetCurrentPaymentAmountWithValidPositiveNumber()
    {
        // Create a mock for AdyenAmountCurrency
        $this->amountCurrencyMock->method('getAmount')->willReturn(100); // Valid positive number
        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($this->amountCurrencyMock);

        // Create an instance of PaymentMethods with mocked dependencies
        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock,
                'chargedCurrency' => $this->chargedCurrencyMock
            ]
        );

        $getCurrentPaymentAmountMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'getCurrentPaymentAmount'
        );
        $result = $getCurrentPaymentAmountMethod->invoke($paymentMethods);

        // Assert that the expected positive float is returned
        $this->assertEquals(100.0, $result);
    }

    public function testGetCurrentPaymentAmountWithNonNumericValue()
    {
        $this->amountCurrencyMock->method('getAmount')->willReturn('invalid_value');
        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($this->amountCurrencyMock);

        $this->quoteMock->expects($this->once())
            ->method('getEntityId')
            ->willReturn(1);

        // Create an instance of PaymentMethods with mocked dependencies
        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock, // Inject the mocked quote
                'chargedCurrency' => $this->chargedCurrencyMock // Inject the mocked chargedCurrency
            ]
        );

        $getCurrentPaymentAmountMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'getCurrentPaymentAmount'
        );

        // Assert that an Exception is thrown when the total amount is not a valid number
        try {
            $getCurrentPaymentAmountMethod->invoke($paymentMethods);
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
            $this->assertEquals("Cannot retrieve a valid grand total from quote ID: `1`. Expected a numeric value.", $e->getMessage());
            return;
        }

        // If no exception is thrown, fail the test
        $this->fail('An expected exception has not been raised.');
    }

    public function testGetCurrentPaymentAmountWithNegativeNumber()
    {
        $this->amountCurrencyMock->method('getAmount')->willReturn(-100); // Negative number
        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($this->amountCurrencyMock);

        // Create an instance of PaymentMethods with mocked dependencies
        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock, // Inject the mocked quote
                'chargedCurrency' => $this->chargedCurrencyMock // Inject the mocked chargedCurrency
            ]
        );
        $getCurrentPaymentAmountMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'getCurrentPaymentAmount'
        );

        // Assert that an Exception is thrown when the total amount is negative
        $this->expectException(Exception::class);
        $getCurrentPaymentAmountMethod->invoke($paymentMethods);
    }

    public function testGetCurrentShopperReferenceWithCustomerId()
    {
        $this->quoteMock->expects($this->any())
            ->method('getCustomerId')
            ->willReturn(123);

        // Create an instance of PaymentMethods with the mocked Quote
        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock
            ]
        );

        $getCurrentShopperReferenceMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'getCurrentShopperReference'
        );

        // Call the method and assert that it returns the expected shopper reference
        $result = $getCurrentShopperReferenceMethod->invoke($paymentMethods);
        $this->assertEquals('123', $result); // Expecting the customerId to be cast to string
    }

    public function testGetCurrentShopperReferenceWithoutCustomerId()
    {
        $this->quoteMock->method('getCustomerId')->willReturn(null);

        // Create an instance of PaymentMethods with the mocked Quote
        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock
            ]
        );
        $getCurrentShopperReferenceMethod = $this->getPrivateMethod(
            PaymentMethods::class,
            'getCurrentShopperReference'
        );

        // Call the method and assert that it returns null when customerId is not set
        $result = $getCurrentShopperReferenceMethod->invoke($paymentMethods);
        $this->assertNull($result);
    }

    public function testIsBankTransfer()
    {
        // Test with bank transfer payment method
        $paymentMethod = 'bankTransferNL';
        $this->assertTrue($this->paymentMethodsHelper->isBankTransfer($paymentMethod));

        // Test with non-bank transfer payment method
        $paymentMethod = 'adyen_cc';
        $this->assertFalse($this->paymentMethodsHelper->isBankTransfer($paymentMethod));
    }

    public function testIsWalletPaymentMethodTrue()
    {
        $this->methodMock->method('getConfigData')
            ->with('is_wallet')
            ->willReturn(true);
        $this->assertTrue($this->paymentMethodsHelper->isWalletPaymentMethod($this->methodMock));

    }

    public function testIsWalletPaymentMethodFalse()
    {
        // Non-wallet payment method
        $this->methodMock->method('getConfigData')
            ->with('is_wallet')
            ->willReturn(false);
        $this->assertFalse($this->paymentMethodsHelper->isWalletPaymentMethod($this->methodMock));
    }

    public function testGetBoletoStatus()
    {
        // Test with valid boleto data
        $this->notificationMock->method('getAdditionalData')
            ->willReturn(json_encode(['boletobancario' => ['originalAmount' => 'BRL 100', 'paidAmount' => 'BRL 90']]));
        $status = $this->paymentMethodsHelper->getBoletoStatus($this->orderMock, $this->notificationMock, 'pending');
        $this->assertEquals('pending', $status);

        // Test with overpaid boleto
        $this->notificationMock->method('getAdditionalData')
            ->willReturn(json_encode(['boletobancario' => ['originalAmount' => 'BRL 100', 'paidAmount' => 'BRL 110']]));
        $status = $this->paymentMethodsHelper->getBoletoStatus($this->orderMock, $this->notificationMock, 'overpaid');
        $this->assertEquals('overpaid', $status);

        // Test with underpaid boleto
        $this->notificationMock->method('getAdditionalData')
            ->willReturn(json_encode(['boletobancario' => ['originalAmount' => 'BRL 100', 'paidAmount' => 'BRL 80']]));
        $status = $this->paymentMethodsHelper->getBoletoStatus($this->orderMock, $this->notificationMock, 'underpaid');
        $this->assertEquals('underpaid', $status);
    }

    public function testIsAlternativePaymentMethod()
    {
        // Test with alternative payment method
        $this->methodMock->method('getConfigData')
            ->with('group')
            ->willReturn(PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS);
        $this->assertTrue($this->paymentMethodsHelper->isAlternativePaymentMethod($this->methodMock));


    }

    public function testIsNotAlternativePaymentMethod()
    {
        // Test with non-alternative payment method
        $this->methodMock->method('getConfigData')
            ->with('group')
            ->willReturn('some_other_group');
        $this->assertFalse($this->paymentMethodsHelper->isAlternativePaymentMethod($this->methodMock));
    }

    public function testGetAlternativePaymentMethodTxVariant()
    {
        // Test with alternative payment method
        $this->methodMock->method('getConfigData')
            ->with('group')
            ->willReturn(PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS);
        $this->methodMock->method('getCode')
            ->willReturn('adyen_some_variant');
        $this->assertEquals('some_variant', $this->paymentMethodsHelper->getAlternativePaymentMethodTxVariant($this->methodMock));
    }

    public function testGetAlternativePaymentMethodTxVariantException()
    {
        // Test with non-alternative payment method
        $this->methodMock->method('getConfigData')
            ->with('group')
            ->willReturn('some_other_group');
        $this->expectException(AdyenException::class);
        $this->paymentMethodsHelper->getAlternativePaymentMethodTxVariant($this->methodMock);
    }

    public function testPaymentMethodSupportsRecurring()
    {
        // Test with supports recurring
        $this->methodMock->method('getConfigData')
            ->with('supports_recurring')
            ->willReturn(true);
        $this->assertTrue($this->paymentMethodsHelper->paymentMethodSupportsRecurring($this->methodMock));
    }

    public function testPaymentMethodNotSupportsRecurring()
    {
        // Test without supports recurring
        $this->methodMock->method('getConfigData')
            ->with('supports_recurring')
            ->willReturn(false);
        $this->assertFalse($this->paymentMethodsHelper->paymentMethodSupportsRecurring($this->methodMock));
    }

    public function testCheckPaymentMethod()
    {
        $this->orderPaymentMock->method('getMethod')
            ->willReturn('some_method');
        $this->assertTrue($this->paymentMethodsHelper->checkPaymentMethod($this->orderPaymentMock, 'some_method'));
    }

    public function testCheckPaymentMethodFalse()
    {
        $this->orderPaymentMock->method('getMethod')
            ->willReturn('some_other_method');
        $this->assertFalse($this->paymentMethodsHelper->checkPaymentMethod($this->orderPaymentMock, 'some_method'));
    }

    public function testGetCcAvailableTypes()
    {
        // Mock adyenHelper
        $adyenCcTypes = [
            'visa' => ['name' => 'Visa'],
            'mastercard' => ['name' => 'MasterCard'],
            'amex' => ['name' => 'American Express']
        ];
        $this->adyenHelperMock->expects($this->once())
            ->method('getAdyenCcTypes')
            ->willReturn($adyenCcTypes);

        $this->configHelperMock->expects($this->once())
            ->method('getAdyenCcConfigData')
            ->with('cctypes')
            ->willReturn('visa,mastercard');

        // Test getCcAvailableTypes
        $expectedResult = [
            'visa' => 'Visa',
            'mastercard' => 'MasterCard'
        ];
        $this->assertEquals($expectedResult, $this->paymentMethodsHelper->getCcAvailableTypes());
    }

    public function testGetCcAvailableTypesByAlt()
    {
        $adyenCcTypes = [
            'visa' => ['name' => 'Visa', 'code_alt' => 'VISA'],
            'mastercard' => ['name' => 'MasterCard', 'code_alt' => 'MC'],
            'amex' => ['name' => 'American Express', 'code_alt' => 'AMEX']
        ];
        $this->adyenHelperMock->expects($this->once())
            ->method('getAdyenCcTypes')
            ->willReturn($adyenCcTypes);

        $this->configHelperMock->expects($this->once())
            ->method('getAdyenCcConfigData')
            ->with('cctypes')
            ->willReturn('visa,mastercard');

        // Test getCcAvailableTypesByAlt
        $expectedResult = [
            'VISA' => 'visa',
            'MC' => 'mastercard'
        ];
        $this->assertEquals($expectedResult, $this->paymentMethodsHelper->getCcAvailableTypesByAlt());
    }

    /**
     * @dataProvider autoCaptureDataProvider
     */
    public function testIsAutoCapture(
        $manualCaptureSupported,
        $captureMode,
        $sepaFlow,
        $paymentCode,
        $autoCaptureOpenInvoice,
        $manualCapturePayPal,
        $expectedResult
    ) {
        $manualCaptureMock = $this->getMockBuilder(ManualCapture::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manualCaptureMock->expects($this->any())->method('isManualCaptureSupported')->willReturn($manualCaptureSupported);

        $this->configHelperMock->expects($this->any())
            ->method('getConfigData')
            ->with('capture_mode', 'adyen_abstract', '1')
            ->willReturn($captureMode);

        $this->configHelperMock->expects($this->any())
            ->method('getConfigData')
            ->with('sepa-flow', 'adyen_abstract', '1')
            ->willReturn($sepaFlow);

        $this->configHelperMock->expects($this->any())
            ->method('getAutoCaptureOpenInvoice')
            ->with( '1')
            ->willReturn($autoCaptureOpenInvoice);

        $this->configHelperMock->expects($this->any())
            ->method('getConfigData')
            ->with( 'paypal_capture_mode','adyen_abstract','1')
            ->willReturn($manualCapturePayPal);

        // Configure the mock to return the method name
        $this->orderPaymentMock->method('getMethod')
            ->willReturn($paymentCode);

        // Configure the order mock to return the payment mock
        $this->orderMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->orderPaymentMock);

        $this->configHelperMock->expects($this->any())
            ->method('getAutoCaptureOpenInvoice')
            ->willReturn($autoCaptureOpenInvoice);

        $paymentMethodsHelper = $this->objectManager->getObject(PaymentMethods::class, [
            'configHelper' => $this->configHelperMock
        ]);

        $result = $paymentMethodsHelper->isAutoCapture($this->orderMock, $paymentCode);

        $this->assertEquals($expectedResult, $result);
    }
    public function autoCaptureDataProvider(): array
    {
        return [
            // Manual capture supported, capture mode manual, sepa flow not authcap
            [true, 'manual', 'notauthcap', 'sepadirectdebit', true, null, true],
            // Manual capture supported, capture mode auto
            [true, 'auto', '', 'sepadirectdebit', true, null, true],
            // Manual capture not supported
            [false, '', '', 'sepadirectdebit', true, null, true]
        ];
    }

    public function testBuildPaymentMethodIconWithSvg()
    {
        // Mock data
        $paymentMethodCode = 'test_method';
        $params = [
            'area' => 'frontend',
            '_secure' => '',
            'theme' => 'Magento/blank'
        ];
        $expectedSVGUrl = 'mocked_svg_url';
        $expectedPNGUrl = 'mocked_png_url';
        $svgAssetMock = $this->createMock(File::class);
        $svgAssetMock->method('getUrl')->willReturn($expectedSVGUrl);
        $pngAssetMock = $this->createMock(File::class);
        $pngAssetMock->method('getUrl')->willReturn($expectedPNGUrl);

        $this->assetRepoMock->method('createAsset')
            ->withConsecutive(
                ["Adyen_Payment::images/logos/{$paymentMethodCode}.svg", $params],
                ["Adyen_Payment::images/logos/{$paymentMethodCode}.png", $params]
            )
            ->willReturnOnConsecutiveCalls($svgAssetMock, $pngAssetMock);

        // Set up asset source mock for SVG asset
        $this->assetSourceMock->method('findSource')
            ->with($svgAssetMock)
            ->willReturn(true);

        // Set up asset source mock for PNG asset
        $this->assetSourceMock->method('findSource')
            ->with($pngAssetMock)
            ->willReturn(false);
        $result = $this->paymentMethodsHelper->buildPaymentMethodIcon($paymentMethodCode, $params);
        $this->assertEquals(['url' => $expectedSVGUrl, 'width' => 77, 'height' => 50], $result);
    }

    public function testBuildPaymentMethodIconWithPngExistsButSvgDoesNot()
    {
        $paymentMethodCode = 'test_method';
        $params = [
            'area' => 'frontend',
            '_secure' => '',
            'theme' => 'Magento/blank'
        ];
        $expectedUrl = "https://checkoutshopper-live.adyen.com/checkoutshopper/images/logos/{$paymentMethodCode}.svg";
        $svgAssetMock = $this->createMock(File::class);
        $pngAssetMock = $this->createMock(File::class);
        $this->assetRepoMock->method('createAsset')
            ->willReturnMap([
                ["Adyen_Payment::images/logos/{$paymentMethodCode}.svg", $params, $svgAssetMock],
                ["Adyen_Payment::images/logos/{$paymentMethodCode}.png", $params, $pngAssetMock]
            ]);
        $this->assetSourceMock->method('findSource')
            ->willReturnMap([
                [$svgAssetMock, false],
                [$pngAssetMock, true]
            ]);
        $pngAssetMock->expects($this->once())->method('getUrl')->willReturn($expectedUrl);
        $result = $this->paymentMethodsHelper->buildPaymentMethodIcon($paymentMethodCode, $params);
        $this->assertEquals(['url' => $expectedUrl, 'width' => 77, 'height' => 50], $result);
    }

    public function testGetPaymentMethodsRequest()
    {
        $merchantAccount = 'TestMerchant';
        $shopperLocale = 'en_US';
        $country = 'NL';
        $amountValue = 100;
        $currencyCode = 'EUR';
        $this->amountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');
        $this->amountCurrencyMock->method('getAmount')->willReturn($amountValue);
        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($this->amountCurrencyMock);
        $this->adyenHelperMock->method('getCurrentLocaleCode')->willReturn($shopperLocale);
        $this->adyenDataHelperMock->method('padShopperReference')->willReturn('123456');
        $this->amountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');
        $expectedResult = [
            "channel" => "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $country,
            "shopperLocale" => $shopperLocale,
            "amount" => ["currency" => $currencyCode]
        ];

        $paymentMethods = $this->objectManager->getObject(
            PaymentMethods::class,
            [
                'quote' => $this->quoteMock,
                'configHelper' => $this->configHelperMock,
                'chargedCurrency' => $this->chargedCurrencyMock,
                'adyenHelper' => $this->adyenHelperMock,
            ]
        );


        $getPaymentMethodsRequest = $this->getPrivateMethod(
            PaymentMethods::class,
            'getPaymentMethodsRequest'
        );
        $result = $getPaymentMethodsRequest->invoke($paymentMethods, $merchantAccount, $this->storeMock, $this->quoteMock, $shopperLocale, $country);


        $this->assertEquals($expectedResult, $result);
    }
}

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
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\Store;

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
    private PaymentTokenRepositoryInterface $paymentTokenRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->configMock = $this->createMock(ScopeConfigInterface::class);
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
        $this->paymentTokenRepository = $this->createMock(PaymentTokenRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);

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
            $this->paymentTokenRepository,
            $this->searchCriteriaBuilder
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

    public function testFetchPaymentMethodsWithNoPaymentMethodsInResponse()
    {
        $country = 'NL';
        $shopperLocale = 'nl_NL';
        $expectedResult = '[]';

        $storeMock = $this->createMock(Store::class);
        $storeMock->method('getId')->willReturn(1);

        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStore'])
            ->getMock();

        $quoteMock->method('getStore')->willReturn($storeMock);
        $quoteMock->setCustomerId(123);

        $reflectionClass = new \ReflectionClass(get_class($this->paymentMethodsHelper));
        $quoteProperty = $reflectionClass->getProperty('quote');
        $quoteProperty->setAccessible(true);
        $quoteProperty->setValue($this->paymentMethodsHelper, $quoteMock);

        $method = $reflectionClass->getMethod('fetchPaymentMethods');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->paymentMethodsHelper, [$country, $shopperLocale]);

        $this->assertEquals($expectedResult, $result);
    }

    public function testFilterStoredPaymentMethods()
    {
        $allowMultistoreTokens = false;
        $customerId = 1;
        $responseData = [
            'storedPaymentMethods' => [
                ['id' => '123', 'name' => 'Visa'],
                ['id' => '456', 'name' => 'Mastercard']
            ]
        ];
        $expectedResult = [
            'storedPaymentMethods' => [
                ['id' => '123', 'name' => 'Visa']
            ]
        ];

        $paymentTokenMock = $this->createMock(\Magento\Vault\Api\Data\PaymentTokenInterface::class);
        $paymentTokenMock->method('getGatewayToken')->willReturn('123');

        $searchCriteriaMock = $this->createMock(\Magento\Framework\Api\SearchCriteriaInterface::class);
        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteriaMock);



        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn('searchCriteria');
        $this->paymentTokenRepository->method('getList')->willReturn(new \Magento\Framework\DataObject(['items' => [$paymentTokenMock]]));

        $reflectionClass = new \ReflectionClass(get_class($this->paymentMethodsHelper));
        $method = $reflectionClass->getMethod('filterStoredPaymentMethods');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->paymentMethodsHelper, [$allowMultistoreTokens, $responseData, $customerId]);

        $this->assertEquals($expectedResult, $result);
    }

//    public function testFetchPaymentMethodsWithPaymentMethodsInResponse()
//    {
//        $country = 'NL';
//        $shopperLocale = 'nl_NL';
//
//        // Prepare expected responses
//        $merchantAccount = 'RokLedinski';
//        $mockPaymentMethodsResponse = [
//            'paymentMethods' => [
//                ['type' => 'card', 'name' => 'Credit Card'],
//                ['type' => 'paypal', 'name' => 'PayPal'],
//            ]
//        ];
//        $expectedResult = json_encode([
//            'paymentMethodsResponse' => $mockPaymentMethodsResponse,
//            'paymentMethodsExtraDetails' => [] // Assume no extra details for simplicity
//        ]);
//
//        // Mock Store and Quote
//        $storeMock = $this->createMock(Store::class);
//        $storeMock->method('getId')->willReturn(1);
//
//        $quoteMock = $this->getMockBuilder(Quote::class)
//            ->disableOriginalConstructor()
//            ->setMethods(['getStore'])
//            ->getMock();
//        $quoteMock->method('getStore')->willReturn($storeMock);
//        $quoteMock->setCustomerId(123);
//
//        // Mock ConfigHelper to return a valid merchant account
//        $configHelperMock = $this->createMock(Config::class);
//        $configHelperMock->method('getAdyenAbstractConfigData')
//            ->with('merchant_account', 1)
//            ->willReturn($merchantAccount);
//
//        // Inject mocks into PaymentMethods helper
//        $this->paymentMethodsHelper = new PaymentMethods($configHelperMock /*, other dependencies */);
//
//        // Inject quote mock into paymentMethodsHelper
//        $reflectionClass = new \ReflectionClass(get_class($this->paymentMethodsHelper));
//        $quoteProperty = $reflectionClass->getProperty('quote');
//        $quoteProperty->setAccessible(true);
//        $quoteProperty->setValue($this->paymentMethodsHelper, $quoteMock);
//
//        // Access and invoke the protected fetchPaymentMethods method
//        $method = $reflectionClass->getMethod('fetchPaymentMethods');
//        $method->setAccessible(true);
//        $result = $method->invokeArgs($this->paymentMethodsHelper, [$country, $shopperLocale]);
//
//        // Assert that the result matches the expected non-empty response
//        $this->assertEquals($expectedResult, $result);
//    }
}

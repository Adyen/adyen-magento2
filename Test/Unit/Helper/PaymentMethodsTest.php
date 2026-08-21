<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Client;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\{ChargedCurrency, Config, Data, Locale, PaymentMethods, PlatformInfo};
use Adyen\Payment\Model\{AdyenAmountCurrency,
    Config\Source\CaptureMode,
    Config\Source\SepaFlow,
    Method\TxVariant,
    Method\TxVariantFactory,
    Notification,
    Ui\Adminhtml\AdyenMotoConfigProvider,
    Ui\AdyenPayByLinkConfigProvider,
    Ui\AdyenPosCloudConfigProvider};
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\View\Asset\{Repository, Source, LocalInterface};
use Magento\Framework\View\Design\{Theme\ThemeProviderInterface};
use Magento\Payment\Helper\Data as MagentoDataHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Adyen\Service\Checkout\PaymentsApi;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use ReflectionClass;
use ReflectionMethod;
use Magento\Checkout\Model\Session as CheckoutSession;
use Adyen\Payment\Helper\ShopperConversionId;

#[CoversClass(PaymentMethods::class)]
class PaymentMethodsTest extends AbstractAdyenTestCase
{
    private PaymentMethods $helper;

    private MockObject $dataHelper;
    private MockObject $configHelper;
    private MockObject $magentoDataHelper;
    private MockObject $serializer;
    private MockObject $config;
    private MockObject $chargedCurrencyMock;
    private MockObject $localeHelper;
    private MockObject $platformInfo;
    private MockObject $quoteMock;
    private MockObject $orderPaymentMock;
    private MockObject $amountCurrencyMock;
    private MockObject $methodMock;
    private MockObject $orderMock;
    private MockObject $notificationMock;
    private MockObject $clientMock;
    private ObjectManager $objectManager;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private PaymentTokenRepositoryInterface $paymentTokenRepositoryInterface;
    private Source $sourceMock;
    private Repository $repositoryMock;
    private MockObject $checkoutSession;
    private MockObject $generateShopperConversionId;
    private MockObject $cartRepository;
    private MockObject $requestInterfaceMock;
    private MockObject $txVariantFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataHelper = $this->createMock(Data::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->magentoDataHelper = $this->createMock(MagentoDataHelper::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->config = $this->createMock(ScopeConfigInterface::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->localeHelper = $this->createMock(Locale::class);

        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->addMethods(['getCustomerId'])
            ->onlyMethods(['getStore','getBillingAddress','getEntityId'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderPaymentMock = $this->createMock(Order\Payment::class);
        $this->amountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $this->methodMock = $this->createMock(MethodInterface::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->notificationMock = $this->createMock(Notification::class);
        $this->clientMock = $this->createMock(Client::class);
        $this->objectManager = new ObjectManager($this);
        $this->repositoryMock = $this->createMock(Repository::class);
        $this->sourceMock = $this->createMock(Source::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->paymentTokenRepositoryInterface = $this->createMock(PaymentTokenRepositoryInterface::class);

        // NEW: extra deps
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->generateShopperConversionId = $this->createMock(ShopperConversionId::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);

        // Default: the session quote has NO shopper_conversion_id
        $sessionQuote = $this->createMock(Quote::class);
        $sessionPayment = $this->getMockBuilder(\Magento\Quote\Model\Quote\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();
        $sessionPayment->method('getAdditionalInformation')
            ->with('shopper_conversion_id')
            ->willReturn(null);
        $sessionQuote->method('getPayment')->willReturn($sessionPayment);
        $this->checkoutSession->method('getQuote')->willReturn($sessionQuote);
        $this->requestInterfaceMock = $this->createMock(RequestInterface::class);
        $this->txVariantFactory = $this->createMock(TxVariantFactory::class);

        $this->helper = new PaymentMethods(
            $this->createMock(Context::class),
            $this->cartRepository,
            $this->config,
            $this->dataHelper,
            $this->createMock(AdyenLogger::class),
            $this->repositoryMock,
            $this->sourceMock,
            $this->createMock(DesignInterface::class),
            $this->createMock(ThemeProviderInterface::class),
            $this->chargedCurrencyMock,
            $this->configHelper,
            $this->magentoDataHelper,
            $this->serializer,
            $this->paymentTokenRepositoryInterface,
            $this->searchCriteriaBuilder,
            $this->localeHelper,
            $this->generateShopperConversionId,
            $this->checkoutSession,
            $this->requestInterfaceMock,
            $this->txVariantFactory
        );
    }

    public function testIsWalletAndAlternativeMethods(): void
    {
        $this->methodMock->method('getConfigData')->willReturnMap([
            ['is_wallet', null, true],
            ['group', null, PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS],
        ]);

        self::assertTrue($this->helper->isWalletPaymentMethod($this->methodMock));
        self::assertTrue($this->helper->isAlternativePaymentMethod($this->methodMock));
    }

    public function testAlternativePaymentMethodTxVariant(): void
    {
        $this->methodMock->method('getConfigData')->willReturn(PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS);
        $this->methodMock->method('getCode')->willReturn('adyen_boleto');
        $this->assertSame('boleto', $this->helper->getAlternativePaymentMethodTxVariant($this->methodMock));
    }

    public function testIsBankTransfer(): void
    {
        self::assertTrue($this->helper->isBankTransfer('bankTransfer_IBAN'));
        self::assertFalse($this->helper->isBankTransfer('paypal'));
    }

    public function testShowLogos(): void
    {
        $this->configHelper->method('getAdyenAbstractConfigData')->willReturn('title_image');
        self::assertTrue($this->helper->showLogos());
    }

    public function testGetCcAvailableTypes(): void
    {
        $this->dataHelper->method('getAdyenCcTypes')->willReturn([
            'visa' => ['name' => 'Visa'],
            'mc' => ['name' => 'MasterCard']
        ]);
        $this->configHelper->method('getAdyenCcConfigData')->willReturn('visa,mc');

        $result = $this->helper->getCcAvailableTypes();
        self::assertEquals(['visa' => 'Visa', 'mc' => 'MasterCard'], $result);
    }

    public function testGetCcAvailableTypesSkipsInvalid(): void
    {
        $this->dataHelper->method('getAdyenCcTypes')->willReturn([
            'visa' => ['name' => 'Visa'],
            'mc' => ['name' => 'MasterCard']
        ]);
        $this->configHelper->method('getAdyenCcConfigData')->willReturn('visa,amex');

        $result = $this->helper->getCcAvailableTypes();
        self::assertEquals(['visa' => 'Visa'], $result);
    }

    public function testIsAdyenPayment(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([
            'adyen_cc' => [],
            'paypal' => []
        ]);

        self::assertTrue($this->helper->isAdyenPayment('adyen_cc'));
        self::assertFalse($this->helper->isAdyenPayment('paypal'));
    }

    public function testPaymentMethodSupportsRecurring(): void
    {
        $this->methodMock->method('getConfigData')->willReturn(true);
        $this->assertTrue($this->helper->paymentMethodSupportsRecurring($this->methodMock));
    }

    public function testCheckPaymentMethod(): void
    {
        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getMethod' => 'adyen_cc'
        ]);
        $this->assertTrue($this->helper->checkPaymentMethod($payment, 'adyen_cc'));
    }

    public function testGetCcAvailableTypesByAlt(): void
    {
        $this->dataHelper->method('getAdyenCcTypes')->willReturn([
            'visa' => ['code_alt' => 'vis'],
            'mc' => ['code_alt' => 'mcrd']
        ]);
        $this->configHelper->method('getAdyenCcConfigData')->willReturn('visa,mc');

        $result = $this->helper->getCcAvailableTypesByAlt();
        $this->assertEquals(['vis' => 'visa', 'mcrd' => 'mc'], $result);
    }

    public function testGetRequiresLineItems(): void
    {
        $this->methodMock->method('getConfigData')->willReturnMap([
            [PaymentMethods::CONFIG_FIELD_IS_OPEN_INVOICE, null, true],
            [PaymentMethods::CONFIG_FIELD_REQUIRES_LINE_ITEMS, null, false]
        ]);

        $this->assertTrue($this->helper->getRequiresLineItems($this->methodMock));
    }

    public function testRemovePaymentMethodsActivation(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([
            'adyen_cc' => [],
            AdyenPayByLinkConfigProvider::CODE => []
        ]);

        $this->configHelper->expects($this->once())
            ->method('removeConfigData')
            ->with('active', 'adyen_cc', 'stores', 1);

        $this->helper->removePaymentMethodsActivation('stores', 1);
    }

    public function testTogglePaymentMethodsActivation(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([
            'adyen_cc' => [],
            'adyen_paypal' => [],
            AdyenPayByLinkConfigProvider::CODE => []
        ]);

        $result = $this->helper->togglePaymentMethodsActivation(true);
        $this->assertContains('adyen_cc', $result);
        $this->assertContains('adyen_paypal', $result);
        $this->assertNotContains(AdyenPayByLinkConfigProvider::CODE, $result);
    }

    public function testGetAdyenPaymentMethods(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([
            'adyen_cc' => [],
            'paypal' => [],
            'adyen_ideal' => []
        ]);

        $result = $this->helper->getAdyenPaymentMethods();
        $this->assertEquals(['adyen_cc', 'adyen_ideal'], $result);
    }

    public function testBuildPaymentMethodIconFallbackToExternal(): void
    {
        $asset = $this->createMock(LocalInterface::class); // instead of AssetInterface

        $this->repositoryMock->method('createAsset')->willReturn($asset);
        $this->sourceMock->method('findSource')->willReturn(false); // simulate both SVG and PNG not found
        $icon = $this->helper->buildPaymentMethodIcon('testmethod', ['theme' => 'Magento/blank']);
        $this->assertStringContainsString('checkoutshopper-live.adyen.com', $icon['url']);
    }

    public function testIsOpenInvoiceFalse(): void
    {
        $this->methodMock->method('getConfigData')->with(PaymentMethods::CONFIG_FIELD_IS_OPEN_INVOICE)->willReturn(false);

        $this->assertFalse($this->helper->isOpenInvoice($this->methodMock));
    }

    public function testGetRequiresLineItems_RequiresLineItemsTrue(): void
    {
        $this->methodMock->method('getConfigData')->willReturnMap([
            [PaymentMethods::CONFIG_FIELD_IS_OPEN_INVOICE, null, false],
            [PaymentMethods::CONFIG_FIELD_REQUIRES_LINE_ITEMS, null, true]
        ]);

        $this->assertTrue($this->helper->getRequiresLineItems($this->methodMock));
    }

    public function testGetRefundRequiresCapturePspreference(): void
    {
        $this->methodMock->method('getConfigData')
            ->with(PaymentMethods::CONFIG_FIELD_REFUND_REQUIRES_CAPTURE_PSPREFERENCE)
            ->willReturn(true);

        $this->assertTrue($this->helper->getRefundRequiresCapturePspreference($this->methodMock));
    }

    public function testCheckPaymentMethodNegative(): void
    {
        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getMethod' => 'adyen_cc'
        ]);
        $this->assertFalse($this->helper->checkPaymentMethod($payment, 'adyen_paypal'));
    }

    public function testGetAlternativePaymentMethodTxVariantThrowsException(): void
    {
        $this->expectException(\Adyen\AdyenException::class);

        $this->methodMock->method('getConfigData')->willReturn('some-other-group');

        $this->helper->getAlternativePaymentMethodTxVariant($this->methodMock);
    }

    public function testIsAdyenPaymentWithEmptyList(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([]);
        $this->assertFalse($this->helper->isAdyenPayment('adyen_cc'));
    }

    public function testTogglePaymentMethodsActivation_Disable(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([
            'adyen_cc' => [],
            'adyen_paypal' => [],
            AdyenMotoConfigProvider::CODE => []
        ]);

        $this->configHelper->method('getIsPaymentMethodsActive')->willReturn(false);

        $result = $this->helper->togglePaymentMethodsActivation(null);
        $this->assertContains('adyen_cc', $result);
        $this->assertContains('adyen_paypal', $result);
        $this->assertNotContains(AdyenMotoConfigProvider::CODE, $result);
    }

    public function testGetBoletoStatusOverpaid(): void
    {
        $order = $this->createConfiguredMock(\Magento\Sales\Model\Order::class, ['getStoreId' => 1]);
        $notification = $this->createMock(\Adyen\Payment\Model\Notification::class);

        $additionalData = ['boletobancario' => [
            'originalAmount' => 'BRL 100.00',
            'paidAmount' => 'BRL 120.00'
        ]];

        $this->serializer
            ->method('unserialize')
            ->willReturn($additionalData);

        $notification->method('getAdditionalData')->willReturn(json_encode($additionalData));
        $this->configHelper
            ->method('getConfigData')
            ->with('order_overpaid_status', 'adyen_boleto', 1)
            ->willReturn('overpaid_status');

        $status = $this->helper->getBoletoStatus($order, $notification, 'default_status');
        $this->assertEquals('overpaid_status', $status);
    }

    /**
     * @dataProvider autoCaptureDataProvider
     */
    public function testIsAutoCapture(
        string $webhookPaymentMethod,
        ?string $webhookMethodCode,
        bool $isOpenInvoice,
        ?string $captureMode,
        ?string $sepaFlow,
        bool $isPaypalManualCapture,
        bool $autoCaptureOpenInvoice,
        string $orderMethodCode,
        ?string $captureModePos,
        bool $expectedResult
    ) {
        $this->orderMock->method('getStoreId')->willReturn(1);
        $this->orderMock->method('getPayment')->willReturn($this->orderPaymentMock);
        $this->orderPaymentMock->method('getMethod')->willReturn($orderMethodCode);

        $this->dataHelper->method('getCcTypesAltData')->willReturn(['visa' => ['manual_capture' => 1]]);

        if (isset($webhookMethodCode)) {
            $webhookMethodInstance = $this->createMock(MethodInterface::class);
            $webhookMethodInstance->method('getCode')->willReturn($webhookMethodCode);
            $webhookMethodInstance->method('getConfigData')->willReturnMap([
                ['supports_manual_capture', null, true],
                [PaymentMethods::CONFIG_FIELD_IS_OPEN_INVOICE, null, $isOpenInvoice],
                ['is_wallet', null, false]
            ]);

            $txVariantMock = $this->createMock(TxVariant::class);
            $txVariantMock->method('getMethodInstance')->willReturn($webhookMethodInstance);
            $txVariantMock->method('getCard')->willReturn(null);
        } else {
            // The tx variant cannot be resolved into a payment method instance.
            $txVariantMock = null;
        }

        $this->txVariantFactory->method('create')
            ->with(['txVariant' => $webhookPaymentMethod])
            ->willReturn($txVariantMock);

        $this->configHelper->method('getCaptureMode')->with(1)->willReturn($captureMode);
        $this->configHelper->method('getSepaFlow')->with(1)->willReturn($sepaFlow);
        $this->configHelper->method('isPaypalManualCapture')->with(1)->willReturn($isPaypalManualCapture);
        $this->configHelper->method('getAutoCaptureOpenInvoice')->with(1)->willReturn($autoCaptureOpenInvoice);
        $this->configHelper->method('getAdyenPosCloudConfigData')
            ->with('capture_mode_pos', 1)
            ->willReturn($captureModePos);

        $result = $this->helper->isAutoCapture($this->orderMock, $webhookPaymentMethod);

        $this->assertEquals($expectedResult, $result);
    }

    public static function autoCaptureDataProvider(): array
    {
        return [
            'SEPA sale overrides global manual capture' => [
                'sepadirectdebit', PaymentMethods::ADYEN_SEPADIRECTDEBIT, false,
                CaptureMode::CAPTURE_MODE_MANUAL, SepaFlow::SEPA_FLOW_SALE,
                false, true, PaymentMethods::ADYEN_SEPADIRECTDEBIT, null, true
            ],
            'SEPA authcap overrides global auto capture' => [
                'sepadirectdebit', PaymentMethods::ADYEN_SEPADIRECTDEBIT, false,
                CaptureMode::CAPTURE_MODE_AUTO, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, true, PaymentMethods::ADYEN_SEPADIRECTDEBIT, null, false
            ],
            'PayPal auto capture overrides global manual capture' => [
                'paypal', PaymentMethods::ADYEN_PAYPAL, false,
                CaptureMode::CAPTURE_MODE_MANUAL, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, true, PaymentMethods::ADYEN_PAYPAL, null, true
            ],
            'PayPal manual capture overrides global auto capture' => [
                'paypal', PaymentMethods::ADYEN_PAYPAL, false,
                CaptureMode::CAPTURE_MODE_AUTO, SepaFlow::SEPA_FLOW_AUTHCAP,
                true, true, PaymentMethods::ADYEN_PAYPAL, null, false
            ],
            'Open invoice auto capture overrides global manual capture' => [
                'klarna', 'adyen_klarna', true,
                CaptureMode::CAPTURE_MODE_MANUAL, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, true, 'adyen_klarna', null, true
            ],
            'Open invoice manual capture overrides global auto capture' => [
                'klarna', 'adyen_klarna', true,
                CaptureMode::CAPTURE_MODE_AUTO, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, false, 'adyen_klarna', null, false
            ],
            'Other payment methods follow global manual capture' => [
                'twint', 'adyen_twint', false,
                CaptureMode::CAPTURE_MODE_MANUAL, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, true, 'adyen_twint', null, false
            ],
            'Other payment methods follow global auto capture' => [
                'twint', 'adyen_twint', false,
                CaptureMode::CAPTURE_MODE_AUTO, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, true, 'adyen_twint', null, true
            ],
            'POS auto capture overrides global manual capture' => [
                'twint', 'adyen_twint', false,
                CaptureMode::CAPTURE_MODE_MANUAL, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, true, AdyenPosCloudConfigProvider::CODE, CaptureMode::CAPTURE_MODE_AUTO, true
            ],
            'POS manual capture overrides global auto capture' => [
                'twint', 'adyen_twint', false,
                CaptureMode::CAPTURE_MODE_AUTO, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, true, AdyenPosCloudConfigProvider::CODE, CaptureMode::CAPTURE_MODE_MANUAL, false
            ],
            'Unresolved tx variant follows global manual capture' => [
                'visa', null, false,
                CaptureMode::CAPTURE_MODE_MANUAL, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, true, PaymentMethods::ADYEN_CC, null, false
            ],
            'Unresolved tx variant follows global auto capture' => [
                'visa', null, false,
                CaptureMode::CAPTURE_MODE_AUTO, SepaFlow::SEPA_FLOW_AUTHCAP,
                false, true, PaymentMethods::ADYEN_CC, null, true
            ]
        ];
    }

    public function testCompareOrderAndWebhookPaymentMethodsAlternativeMatch(): void
    {
        $this->methodMock->method('getConfigData')->willReturnMap([
            ['is_wallet', null, false],
            ['group', null, PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS]
        ]);
        $this->methodMock->method('getCode')->willReturn('adyen_boleto');
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getMethodInstance')->willReturn($this->methodMock);
        $payment->method('getCcType')->willReturn(null);

        $order = $this->createConfiguredMock(\Magento\Sales\Model\Order::class, [
            'getPayment' => $payment
        ]);

        $notification = $this->createMock(\Adyen\Payment\Model\Notification::class);
        $notification->method('getPaymentMethod')->willReturn('boleto');

        $this->assertTrue($this->helper->compareOrderAndWebhookPaymentMethods($order, $notification));
    }

    public function testGetBoletoStatusUnderpaid(): void
    {
        $order = $this->createConfiguredMock(\Magento\Sales\Model\Order::class, ['getStoreId' => 1]);
        $notification = $this->createMock(\Adyen\Payment\Model\Notification::class);

        $additionalData = ['boletobancario' => [
            'originalAmount' => 'BRL 100.00',
            'paidAmount' => 'BRL 80.00'
        ]];

        $this->serializer->method('unserialize')->willReturn($additionalData);
        $notification->method('getAdditionalData')->willReturn(json_encode($additionalData));

        $this->configHelper->method('getConfigData')
            ->with('order_underpaid_status', 'adyen_boleto', 1)
            ->willReturn('underpaid_status');

        $status = $this->helper->getBoletoStatus($order, $notification, 'default_status');
        $this->assertEquals('underpaid_status', $status);
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
            $this->helper->compareOrderAndWebhookPaymentMethods($this->orderMock, $this->notificationMock)
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

    public function testFilterStoredPaymentMethodsWithFiltering(): void
    {
        $paymentToken = $this->createConfiguredMock(\Magento\Vault\Api\Data\PaymentTokenInterface::class, [
            'getGatewayToken' => 'token123'
        ]);

        $searchCriteria = $this->createMock(\Magento\Framework\Api\SearchCriteria::class);
        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $tokenList = $this->createMock(\Magento\Vault\Api\Data\PaymentTokenSearchResultsInterface::class);
        $tokenList->method('getItems')->willReturn([$paymentToken]);

        $this->paymentTokenRepositoryInterface->method('getList')->willReturn($tokenList);

        $helper = new PaymentMethods(
            $this->createMock(Context::class),
            $this->createMock(CartRepositoryInterface::class),
            $this->config,
            $this->dataHelper,
            $this->createMock(AdyenLogger::class),
            $this->repositoryMock,
            $this->sourceMock,
            $this->createMock(DesignInterface::class),
            $this->createMock(ThemeProviderInterface::class),
            $this->chargedCurrencyMock,
            $this->configHelper,
            $this->magentoDataHelper,
            $this->serializer,
            $this->paymentTokenRepositoryInterface,
            $this->searchCriteriaBuilder,
            $this->localeHelper,
            $this->generateShopperConversionId,
            $this->checkoutSession,
            $this->requestInterfaceMock,
            $this->txVariantFactory
        );


        $responseData = [
            'storedPaymentMethods' => [
                ['id' => 'token123'],
                ['id' => 'token999']
            ]
        ];

        $filtered = $this->invokeMethod($helper, 'filterStoredPaymentMethods', [false, $responseData, 1]);

        $this->assertCount(1, $filtered['storedPaymentMethods']);
        $this->assertEquals('token123', $filtered['storedPaymentMethods'][0]['id']);
    }

    public function testFetchPaymentMethodsWithNoMerchantAccount(): void
    {
        $quote = $this->createMock(\Magento\Quote\Model\Quote::class);
        $quote->method('getStore')->willReturn(
            $this->createConfiguredMock(\Magento\Store\Model\Store::class, ['getId' => 1])
        );

        $this->configHelper->method('getAdyenAbstractConfigData')->willReturn(null);

        $this->invokeMethod($this->helper, 'setQuote', [$quote]);

        $result = $this->invokeMethod($this->helper, 'fetchPaymentMethods', [null, null, null]);
        $this->assertEquals(json_encode([]), $result);
    }

    public function testGetCurrentCountryCodeFromBilling(): void
    {
        $billing = $this->createConfiguredMock(\Magento\Quote\Model\Quote\Address::class, [
            'getCountryId' => 'NL'
        ]);
        $quote = $this->createConfiguredMock(\Magento\Quote\Model\Quote::class, [
            'getBillingAddress' => $billing
        ]);

        $store = $this->createConfiguredMock(\Magento\Store\Model\Store::class, [
            'getCode' => 'default'
        ]);

        $this->invokeMethod($this->helper, 'setQuote', [$quote]);

        $result = $this->invokeMethod($this->helper, 'getCurrentCountryCode', [$store]);
        $this->assertEquals('NL', $result);
    }

    public function testGetCurrentCountryCodeFallback(): void
    {
        $billing = $this->createConfiguredMock(\Magento\Quote\Model\Quote\Address::class, [
            'getCountryId' => null
        ]);
        $quote = $this->createConfiguredMock(\Magento\Quote\Model\Quote::class, [
            'getBillingAddress' => $billing
        ]);

        $store = $this->createConfiguredMock(\Magento\Store\Model\Store::class, [
            'getCode' => 'default'
        ]);

        $this->config->method('getValue')->willReturn('DE');

        $this->invokeMethod($this->helper, 'setQuote', [$quote]);
        $result = $this->invokeMethod($this->helper, 'getCurrentCountryCode', [$store]);
        $this->assertEquals('DE', $result);
    }

    public function testGetCurrentPaymentAmountReturnsFloat(): void
    {
        $amountCurrency = $this->createConfiguredMock(AdyenAmountCurrency::class, [
            'getAmount' => 123.45
        ]);

        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($amountCurrency);

        $quote = $this->createMock(\Magento\Quote\Model\Quote::class);
        $this->invokeMethod($this->helper, 'setQuote', [$quote]);

        $this->assertEquals(123.45, $this->invokeMethod($this->helper, 'getCurrentPaymentAmount'));
    }


    public function testGetCurrentPaymentAmountThrowsOnInvalid(): void
    {
        $this->expectException(\Adyen\AdyenException::class);

        $amountCurrency = $this->createConfiguredMock(AdyenAmountCurrency::class, [
            'getAmount' => 'not-a-float'
        ]);
        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($amountCurrency);

        $quote = $this->createMock(\Magento\Quote\Model\Quote::class);
        $this->invokeMethod($this->helper, 'setQuote', [$quote]);

        $this->invokeMethod($this->helper, 'getCurrentPaymentAmount');
    }


    public function testGetCurrentShopperReferenceReturnsId(): void
    {
        $this->quoteMock->expects($this->any())
            ->method('getCustomerId')
            ->willReturn(123);

        $this->invokeMethod($this->helper, 'setQuote', [$this->quoteMock]);

        $this->assertEquals('123', $this->invokeMethod($this->helper, 'getCurrentShopperReference'));
    }

    public function testGetCurrentShopperReferenceReturnsNull(): void
    {
        $this->quoteMock->expects($this->any())
            ->method('getCustomerId')
            ->willReturn(null);

        $this->invokeMethod($this->helper, 'setQuote', [$this->quoteMock]);

        $this->assertNull($this->invokeMethod($this->helper, 'getCurrentShopperReference'));
    }

    public function testGetPaymentMethodsRequest()
    {
        $merchantAccount = 'TestMerchant';
        $shopperLocale = 'en_US';
        $country = 'NL';
        $currencyCode = 'EUR';

        $this->amountCurrencyMock->method('getCurrencyCode')->willReturn($currencyCode);
        $this->amountCurrencyMock->method('getAmount')->willReturn(100);
        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($this->amountCurrencyMock);

        $this->localeHelper->method('getCurrentLocaleCode')->willReturn($shopperLocale);
        $this->dataHelper->method('padShopperReference')->willReturn('123456');

        $this->invokeMethod($this->helper, 'setQuote', [$this->quoteMock]);

        $expected = [
            "channel" => "Web",
            "merchantAccount" => $merchantAccount,
            "countryCode" => $country,
            "shopperLocale" => $shopperLocale,
            "amount" => ["currency" => $currencyCode]
        ];

        $getPaymentMethodsRequest = $this->getPrivateMethod(PaymentMethods::class, 'getPaymentMethodsRequest');

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getCode')->willReturn('default');

        $result = $getPaymentMethodsRequest->invoke(
            $this->helper,
            $merchantAccount,
            $storeMock,
            $this->quoteMock,
            $shopperLocale,
            $country
        );

        $this->assertEquals($expected, $result);
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

    public function testShowLogosPaymentMethodsReturnsUnchangedWhenDisabled(): void
    {
        $this->configHelper->method('getAdyenAbstractConfigData')->willReturn('title_text');

        $methods = [['type' => 'scheme']];
        $extraDetails = ['scheme' => ['existing' => true]];

        $result = $this->invokeMethod($this->helper, 'showLogosPaymentMethods', [$methods, $extraDetails]);
        $this->assertEquals($extraDetails, $result);
    }

    public function testAddExtraConfigurationToPaymentMethods(): void
    {
        $amountCurrency = $this->createConfiguredMock(AdyenAmountCurrency::class, [
            'getAmount' => 123.45
        ]);

        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($amountCurrency);

        $quote = $this->createMock(\Magento\Quote\Model\Quote::class);
        $this->invokeMethod($this->helper, 'setQuote', [$quote]);

        $this->dataHelper->method('formatAmount')->willReturn(9999);

        $this->invokeMethod($this->helper, 'setQuote', [$quote]);

        $paymentMethods = [['type' => 'scheme']];
        $result = $this->invokeMethod($this->helper, 'addExtraConfigurationToPaymentMethods', [$paymentMethods, []]);

        $this->assertArrayHasKey('scheme', $result);
        $this->assertEquals(9999, $result['scheme']['configuration']['amount']['value']);
    }

    public function testGetPaymentMethodsResponseReturnsEmptyOnAdyenException(): void
    {
        $checkoutServiceMock = $this->createMock(PaymentsApi::class);

        $this->dataHelper->method('initializeAdyenClient')->willReturn($this->clientMock);
        $this->dataHelper->method('initializePaymentsApi')->willReturn($checkoutServiceMock);

        // The call inside getPaymentMethodsResponse should throw
        $checkoutServiceMock->method('paymentMethods')->willThrowException(new \Adyen\AdyenException('boom'));

        $store = $this->createConfiguredMock(\Magento\Store\Model\Store::class, ['getId' => 1]);

        $result = $this->invokeMethod($this->helper, 'getPaymentMethodsResponse', [[], $store]);
        $this->assertEquals([], $result);
    }

    public function testTogglePaymentMethodsActivationBasedOnConfig(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([
            'adyen_cc' => [],
            'adyen_ideal' => [],
            AdyenPayByLinkConfigProvider::CODE => []
        ]);

        $this->configHelper->method('getIsPaymentMethodsActive')->willReturn(true);

        $result = $this->helper->togglePaymentMethodsActivation(null);
        $this->assertContains('adyen_cc', $result);
        $this->assertContains('adyen_ideal', $result);
        $this->assertNotContains(AdyenPayByLinkConfigProvider::CODE, $result);
    }

    public function testBuildPaymentMethodIconSvgFound(): void
    {
        $asset = $this->createMock(\Magento\Framework\View\Asset\LocalInterface::class);
        $asset->method('getUrl')->willReturn('https://example.com/icon.svg');

        $this->repositoryMock->method('createAsset')->willReturn($asset);

        $this->sourceMock->method('findSource')->willReturn(true); // SVG found

        $result = $this->helper->buildPaymentMethodIcon('scheme', []);
        $this->assertStringContainsString('.svg', $result['url']);
    }

    public function testBuildPaymentMethodIconPngFallback(): void
    {
        $asset = $this->createMock(\Magento\Framework\View\Asset\LocalInterface::class);
        $asset->method('getUrl')->willReturn('https://example.com/icon.png');

        $this->repositoryMock->method('createAsset')->willReturn($asset);

        $this->sourceMock->method('findSource')->will($this->onConsecutiveCalls(false, true)); // SVG not found, PNG found

        $result = $this->helper->buildPaymentMethodIcon('scheme', []);
        $this->assertStringContainsString('.png', $result['url']);
    }

    public function testGetAdyenPaymentMethodsOnlyAdyenPrefixed(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([
            'adyen_cc' => [],
            'adyen_test' => [],
            'paypal' => [],
            'stripe' => []
        ]);

        $methods = $this->helper->getAdyenPaymentMethods();
        $this->assertEquals(['adyen_cc', 'adyen_test'], $methods);
    }

    public function testGetPaymentMethodsReturnsEmptyWhenQuoteNotFound(): void
    {
        $this->cartRepository->method('getActive')->willReturn(null);
        $result = $this->helper->getPaymentMethods(999);
        $this->assertSame('', $result);
    }

    public function testRemovePaymentMethodsActivationSkipsExcluded(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([
            'adyen_cc' => [],
            'adyen_pos_cloud' => []
        ]);

        $this->configHelper->expects($this->once())
            ->method('removeConfigData')
            ->with('active', 'adyen_cc', 'websites', 10);

        $this->helper->removePaymentMethodsActivation('websites', 10);
    }

    public function testCompareOrderAndWebhookWalletMethod(): void
    {
        $this->methodMock->method('getConfigData')->willReturnMap([
            ['is_wallet', null, true]
        ]);

        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getMethodInstance')->willReturn($this->methodMock);
        $payment->method('getMethod')->willReturn('adyen_googlepay');

        $order = $this->createConfiguredMock(\Magento\Sales\Model\Order::class, [
            'getPayment' => $payment
        ]);

        $notification = $this->createMock(\Adyen\Payment\Model\Notification::class);
        $notification->method('getPaymentMethod')->willReturn('paywithgoogle');

        $this->assertTrue($this->helper->compareOrderAndWebhookPaymentMethods($order, $notification));
    }

    public function testShowLogosReturnsFalseIfNotTitleImage(): void
    {
        $this->configHelper->method('getAdyenAbstractConfigData')->willReturn('title_text');
        $this->assertFalse($this->helper->showLogos());
    }

    public function testPaymentMethodSupportsRecurringFalse(): void
    {
        $this->methodMock->method('getConfigData')->with('supports_recurring')->willReturn(false);
        $this->assertFalse($this->helper->paymentMethodSupportsRecurring($this->methodMock));
    }

    public function testGetBoletoStatusWithMissingAmounts(): void
    {
        $order = $this->createConfiguredMock(\Magento\Sales\Model\Order::class, ['getStoreId' => 1]);
        $notification = $this->createMock(\Adyen\Payment\Model\Notification::class);

        $data = ['boletobancario' => []];

        $this->serializer->method('unserialize')->willReturn($data);
        $notification->method('getAdditionalData')->willReturn(json_encode($data));

        $result = $this->helper->getBoletoStatus($order, $notification, 'default');
        $this->assertEquals('default', $result);
    }

    public function testGetPaymentMethodsRequestAddsShopperConversionIdWhenPresent()
    {
        $merchantAccount = 'TestMerchant';
        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->method('getCode')->willReturn('default');

        // Billing address so getCurrentCountryCode() doesn't hit null
        $billing = $this->createConfiguredMock(\Magento\Quote\Model\Quote\Address::class, [
            'getCountryId' => 'NL',
            'getTelephone' => '0612345678'
        ]);
        $this->quoteMock->method('getBillingAddress')->willReturn($billing);
        $this->invokeMethod($this->helper, 'setQuote', [$this->quoteMock]);

        // Currency + amount setup (IMPORTANT: make amount numeric)
        $this->amountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');
        $this->amountCurrencyMock->method('getAmount')->willReturn(100.00); // ✅ prevents AdyenException
        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($this->amountCurrencyMock);

        $this->localeHelper->method('getCurrentLocaleCode')->willReturn('en_US');

        $this->dataHelper->method('formatAmount')->willReturn(10000);

        $sessionQuote = $this->createMock(Quote::class);
        $sessionPayment = $this->getMockBuilder(\Magento\Quote\Model\Quote\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();
        $sessionPayment->method('getAdditionalInformation')
            ->with('shopper_conversion_id')
            ->willReturn(json_encode('anything-non-empty'));
        $sessionQuote->method('getPayment')->willReturn($sessionPayment);
        $this->checkoutSession->method('getQuote')->willReturn($sessionQuote);

        $this->generateShopperConversionId->method('getShopperConversionId')->willReturn('scid-123');

        $getPaymentMethodsRequest = $this->getPrivateMethod(\Adyen\Payment\Helper\PaymentMethods::class, 'getPaymentMethodsRequest');

        $result = $getPaymentMethodsRequest->invoke(
            $this->helper,
            $merchantAccount,
            $storeMock,
            $this->quoteMock,
            null,
            null
        );

        $this->assertSame('NL', $result['countryCode']);
        $this->assertSame(10000, $result['amount']['value']);
    }

    public function testGetApiResponseFetchesAndCachesWhenEmpty(): void
    {
        $quoteId = 123;
        $countryId = 'NL';
        $channel = 'Web';
        $apiResponse = '{"paymentMethods":"from-api"}';

        // Create a mocked version of SUT to mock `getPaymentMethods()` method.
        $helper = $this->getMockBuilder(PaymentMethods::class)
            ->setConstructorArgs([
                $this->createMock(Context::class),
                $this->cartRepository,
                $this->config,
                $this->dataHelper,
                $this->createMock(AdyenLogger::class),
                $this->repositoryMock,
                $this->sourceMock,
                $this->createMock(DesignInterface::class),
                $this->createMock(ThemeProviderInterface::class),
                $this->chargedCurrencyMock,
                $this->configHelper,
                $this->magentoDataHelper,
                $this->serializer,
                $this->paymentTokenRepositoryInterface,
                $this->searchCriteriaBuilder,
                $this->localeHelper,
                $this->generateShopperConversionId,
                $this->checkoutSession,
                $this->requestInterfaceMock,
                $this->txVariantFactory
            ])
            ->onlyMethods(['getPaymentMethods'])
            ->getMock();

        $billingAddressMock = $this->createMock(AddressInterface::class);
        $billingAddressMock->expects($this->once())
            ->method('getCountryId')
            ->willReturn($countryId);

        $quoteMock = $this->createMock(CartInterface::class);
        $quoteMock->expects($this->once())
            ->method('getId')
            ->willReturn($quoteId);
        $quoteMock->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($billingAddressMock);

        $this->requestInterfaceMock->expects($this->once())
            ->method('getParam')
            ->with('channel')
            ->willReturn($channel);

        $helper->expects($this->once())
            ->method('getPaymentMethods')
            ->with($quoteId, $countryId, null, $channel)
            ->willReturn($apiResponse);

        $result = $helper->getApiResponse($quoteMock);
        $this->assertSame($apiResponse, $result);

        $reflection = new ReflectionClass($helper);
        $property = $reflection->getProperty('paymentMethodsApiResponse');
        $property->setAccessible(true);
        $this->assertSame($apiResponse, $property->getValue($helper));
    }

    public function testGetApiResponseReturnsCachedWhenAlreadySet(): void
    {
        $cached = '{"paymentMethods":"cached"}';

        // Create a mocked version of SUT to mock `getPaymentMethods()` method.
        $helper = $this->getMockBuilder(PaymentMethods::class)
            ->setConstructorArgs([
                $this->createMock(Context::class),
                $this->cartRepository,
                $this->config,
                $this->dataHelper,
                $this->createMock(AdyenLogger::class),
                $this->repositoryMock,
                $this->sourceMock,
                $this->createMock(DesignInterface::class),
                $this->createMock(ThemeProviderInterface::class),
                $this->chargedCurrencyMock,
                $this->configHelper,
                $this->magentoDataHelper,
                $this->serializer,
                $this->paymentTokenRepositoryInterface,
                $this->searchCriteriaBuilder,
                $this->localeHelper,
                $this->generateShopperConversionId,
                $this->checkoutSession,
                $this->requestInterfaceMock,
                $this->txVariantFactory
            ])
            ->onlyMethods(['getPaymentMethods'])
            ->getMock();

        $reflection = new ReflectionClass($helper);
        $property = $reflection->getProperty('paymentMethodsApiResponse');
        $property->setAccessible(true);
        $property->setValue($helper, $cached);

        $this->requestInterfaceMock->expects($this->never())
            ->method('getParam');
        $helper->expects($this->never())
            ->method('getPaymentMethods');

        $quoteMock = $this->createMock(CartInterface::class);
        $result = $helper->getApiResponse($quoteMock);

        $this->assertSame($cached, $result);
    }

    public function testApplyPaymentMethodTitleOverridesReplacesMatchingNames(): void
    {
        $overrides = ['scheme' => 'Kreditkarte', 'klarna' => 'Pay Later'];

        $this->configHelper->method('getPaymentMethodTitleOverrides')
            ->with(1)
            ->willReturn($overrides);

        $paymentMethods = [
            ['type' => 'scheme', 'name' => 'Cards'],
            ['type' => 'klarna', 'name' => 'Klarna'],
            ['type' => 'ideal',  'name' => 'iDEAL'],
        ];

        $result = $this->invokeMethod($this->helper, 'applyPaymentMethodTitleOverrides', [$paymentMethods, 1]);

        $this->assertSame('Kreditkarte', $result[0]['name']);
        $this->assertSame('Pay Later',   $result[1]['name']);
        $this->assertSame('iDEAL',       $result[2]['name']);
    }

    public function testApplyPaymentMethodTitleOverridesReturnUnchangedWhenNoOverrides(): void
    {
        $this->configHelper->method('getPaymentMethodTitleOverrides')
            ->with(1)
            ->willReturn([]);

        $paymentMethods = [
            ['type' => 'scheme', 'name' => 'Cards'],
            ['type' => 'ideal',  'name' => 'iDEAL'],
        ];

        $result = $this->invokeMethod($this->helper, 'applyPaymentMethodTitleOverrides', [$paymentMethods, 1]);

        $this->assertSame('Cards', $result[0]['name']);
        $this->assertSame('iDEAL', $result[1]['name']);
    }

    public function testApplyPaymentMethodTitleOverridesIgnoresMethodsWithoutType(): void
    {
        $this->configHelper->method('getPaymentMethodTitleOverrides')
            ->willReturn(['scheme' => 'Kreditkarte']);

        $paymentMethods = [
            ['name' => 'No Type Method'],
            ['type' => 'scheme', 'name' => 'Cards'],
        ];

        $result = $this->invokeMethod($this->helper, 'applyPaymentMethodTitleOverrides', [$paymentMethods, 1]);

        $this->assertSame('No Type Method', $result[0]['name']);
        $this->assertSame('Kreditkarte',    $result[1]['name']);
    }
}

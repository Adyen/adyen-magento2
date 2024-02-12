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
                    'adyen_cc_vault' => [],
                    'adyen_pos_cloud' => [],
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
}

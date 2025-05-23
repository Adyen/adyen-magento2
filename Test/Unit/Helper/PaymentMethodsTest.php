<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Model\Ui\Adminhtml\AdyenMotoConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Payment\Helper\Data as MagentoDataHelper;

#[CoversClass(PaymentMethods::class)]
class PaymentMethodsTest extends TestCase
{
    private PaymentMethods $helper;

    private MockObject $dataHelper;
    private MockObject $configHelper;
    private MagentoDataHelper $magentoDataHelper;

    protected function setUp(): void
    {
        $this->dataHelper = $this->createMock(Data::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->magentoDataHelper = $this->createMock(MagentoDataHelper::class);

        $this->helper = new PaymentMethods(
            $this->createMock(Context::class),
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class),
            $this->dataHelper,
            $this->createMock(\Magento\Framework\Locale\ResolverInterface::class),
            $this->createMock(\Adyen\Payment\Logger\AdyenLogger::class),
            $this->createMock(\Magento\Framework\View\Asset\Repository::class),
            $this->createMock(\Magento\Framework\App\RequestInterface::class),
            $this->createMock(\Magento\Framework\View\Asset\Source::class),
            $this->createMock(\Magento\Framework\View\DesignInterface::class),
            $this->createMock(\Magento\Framework\View\Design\Theme\ThemeProviderInterface::class),
            $this->createMock(\Adyen\Payment\Helper\ChargedCurrency::class),
            $this->configHelper,
            $this->magentoDataHelper,
            $this->createMock(SerializerInterface::class),
            $this->createMock(PaymentTokenRepositoryInterface::class),
            $this->createMock(\Magento\Framework\Api\SearchCriteriaBuilder::class),
            $this->createMock(Locale::class),
            $this->createMock(PlatformInfo::class)
        );
    }

    public function testIsWalletAndAlternativeMethods(): void
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('getConfigData')->willReturnMap([
            ['is_wallet', null, true],
            ['group', null, PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS],
        ]);
        $this->assertTrue($this->helper->isWalletPaymentMethod($method));
        $this->assertTrue($this->helper->isAlternativePaymentMethod($method));
    }

    public function testAlternativePaymentMethodTxVariant(): void
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('getConfigData')->willReturn(PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS);
        $method->method('getCode')->willReturn('adyen_boleto');
        $this->assertSame('boleto', $this->helper->getAlternativePaymentMethodTxVariant($method));
    }

    public function testPaymentMethodSupportsRecurring(): void
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('getConfigData')->willReturn(true);
        $this->assertTrue($this->helper->paymentMethodSupportsRecurring($method));
    }

    public function testCheckPaymentMethod(): void
    {
        $payment = $this->createConfiguredMock(\Magento\Sales\Model\Order\Payment::class, [
            'getMethod' => 'adyen_cc'
        ]);
        $this->assertTrue($this->helper->checkPaymentMethod($payment, 'adyen_cc'));
    }

    public function testIsBankTransfer(): void
    {
        $this->assertTrue($this->helper->isBankTransfer('bankTransfer_IBAN'));
        $this->assertFalse($this->helper->isBankTransfer('paypal'));
    }

    public function testShowLogos(): void
    {
        $this->configHelper->method('getAdyenAbstractConfigData')->willReturn('title_image');
        $this->assertTrue($this->helper->showLogos());
    }

    public function testGetCcAvailableTypes(): void
    {
        $this->dataHelper->method('getAdyenCcTypes')->willReturn([
            'visa' => ['name' => 'Visa'],
            'mc' => ['name' => 'MasterCard']
        ]);
        $this->configHelper->method('getAdyenCcConfigData')->willReturn('visa,mc');

        $result = $this->helper->getCcAvailableTypes();
        $this->assertEquals(['visa' => 'Visa', 'mc' => 'MasterCard'], $result);
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
        $method = $this->createMock(MethodInterface::class);
        $method->method('getConfigData')->willReturnMap([
            [PaymentMethods::CONFIG_FIELD_IS_OPEN_INVOICE, null, true],
            [PaymentMethods::CONFIG_FIELD_REQUIRES_LINE_ITEMS, null, false]
        ]);

        $this->assertTrue($this->helper->getRequiresLineItems($method));
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

    public function testIsAdyenPayment(): void
    {
        $this->magentoDataHelper->method('getPaymentMethodList')->willReturn([
            'adyen_cc' => [],
            'adyen_paypal' => [],
            'paypal' => []
        ]);

        $this->assertTrue($this->helper->isAdyenPayment('adyen_cc'));
        $this->assertFalse($this->helper->isAdyenPayment('paypal'));
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

}

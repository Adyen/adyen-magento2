<?php

namespace Adyen\Payment\Test\Plugin;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\Method\Adapter;
use Adyen\Payment\Plugin\PaymentMethodTitleOverride;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentMethodTitleOverrideTest extends AbstractAdyenTestCase
{
    private PaymentMethodTitleOverride $plugin;
    private Config|MockObject $configHelperMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configHelperMock = $this->createMock(Config::class);
        $this->plugin = new PaymentMethodTitleOverride($this->configHelperMock);
    }

    public static function titleDataProvider(): array
    {
        return [
            'override for alternative payment method' => [
                'adyen_klarna', ['klarna' => 'Pay later'], 'Pay later'
            ],
            'override with mixed case tx variant' => [
                'adyen_onlineBanking_PL', ['onlineBanking_PL' => 'Przelewy 24'], 'Przelewy 24'
            ],
            'override for cards uses scheme' => [
                'adyen_cc', ['scheme' => 'Credit card'], 'Credit card'
            ],
            'override for boleto uses boletobancario' => [
                'adyen_boleto', ['boletobancario' => 'Boleto'], 'Boleto'
            ],
            'only the leading prefix is removed' => [
                'adyen_foo_adyen_bar', ['foo_bar' => 'Wrong', 'foo_adyen_bar' => 'Foo'], 'Foo'
            ],
            'override for another payment method' => [
                'adyen_klarna', ['paypal' => 'PayPal Express'], 'Configured title'
            ],
            'no overrides configured' => [
                'adyen_klarna', [], 'Configured title'
            ],
            'blank override' => [
                'adyen_klarna', ['klarna' => ' '], 'Configured title'
            ],
        ];
    }

    #[DataProvider('titleDataProvider')]
    public function testAfterGetTitle(string $code, array $overrides, string $expectedTitle): void
    {
        $adapterStub = $this->createConfiguredStub(Adapter::class, [
            'getCode' => $code,
            'getStore' => 2
        ]);

        $this->configHelperMock->expects($this->once())
            ->method('getPaymentMethodTitleOverrides')
            ->with(2)
            ->willReturn($overrides);

        $this->assertSame(
            $expectedTitle,
            $this->plugin->afterGetTitle($adapterStub, 'Configured title')
        );
    }

    public function testAfterGetTitleIgnoresCodesWithoutAdyenPrefix(): void
    {
        $adapterStub = $this->createConfiguredStub(Adapter::class, [
            'getCode' => 'custom_adyen_klarna',
            'getStore' => 2
        ]);

        $this->configHelperMock->expects($this->never())
            ->method('getPaymentMethodTitleOverrides');

        $this->assertSame(
            'Configured title',
            $this->plugin->afterGetTitle($adapterStub, 'Configured title')
        );
    }
}

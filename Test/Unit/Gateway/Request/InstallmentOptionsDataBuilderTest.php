<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Gateway\Request\InstallmentOptionsDataBuilder;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data as AdyenDataHelper;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class InstallmentOptionsDataBuilderTest extends AbstractAdyenTestCase
{
    private Config&MockObject $configHelper;
    private SerializerInterface&MockObject $serializer;
    private StoreManagerInterface&MockObject $storeManager;
    private AdyenDataHelper&MockObject $adyenHelper;

    private StoreInterface&MockObject $store;
    private PaymentDataObject&MockObject $paymentDataObject;
    private OrderAdapterInterface&MockObject $order;

    private InstallmentOptionsDataBuilder $subject;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(Config::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->adyenHelper = $this->createMock(AdyenDataHelper::class);

        $this->store = $this->createMock(StoreInterface::class);
        $this->paymentDataObject = $this->createMock(PaymentDataObject::class);
        $this->order = $this->createMock(OrderAdapterInterface::class);

        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->store->method('getId')->willReturn(1);

        $this->paymentDataObject->method('getOrder')->willReturn($this->order);

        $this->subject = new InstallmentOptionsDataBuilder(
            $this->configHelper,
            $this->serializer,
            $this->storeManager,
            $this->adyenHelper
        );
    }

    public function testBuildReturnsEmptyArrayWhenInstallmentsDisabled(): void
    {
        $this->order->method('getGrandTotalAmount')->willReturn(100.0);

        $this->configHelper
            ->expects($this->once())
            ->method('getAdyenCcConfigData')
            ->with('enable_installments', 1)
            ->willReturn(false);

        $result = $this->subject->build([
            'payment' => $this->paymentDataObject
        ]);

        $this->assertSame([], $result);
    }

    public function testBuildReturnsEmptyArrayWhenInstallmentsConfigIsEmpty(): void
    {
        $this->order->method('getGrandTotalAmount')->willReturn(100.0);

        $this->configHelper
            ->method('getAdyenCcConfigData')
            ->willReturnMap([
                ['enable_installments', 1, true],
                ['installments', 1, ''], // empty raw
            ]);

        $result = $this->subject->build([
            'payment' => $this->paymentDataObject
        ]);

        $this->assertSame([], $result);
    }

    public function testBuildReturnsInstallmentOptionsForEligibleTiersAndAddsOne(): void
    {
        $this->order->method('getGrandTotalAmount')->willReturn(65.0);

        $this->configHelper
            ->method('getAdyenCcConfigData')
            ->willReturnMap([
                ['enable_installments', 1, true],
                ['installments', 1, 'serialized-config'],
            ]);

        $this->adyenHelper
            ->method('getCcTypesAltData')
            ->willReturn([
                'visa' => ['code' => 'visa'],
                'mc'   => ['code' => 'mc'],
            ]);

        $this->serializer
            ->expects($this->once())
            ->method('unserialize')
            ->with('serialized-config')
            ->willReturn([
                // Eligible tiers for 65: >=20 and >=60, not >=100
                'visa' => [
                    20  => [2],
                    60  => [1],
                    100 => [3],
                ],
            ]);

        $result = $this->subject->build([
            'payment' => $this->paymentDataObject
        ]);

        $this->assertSame(
            [
                'body' => [
                    'installmentOptions' => [
                        'visa' => [
                            'values' => [1, 2],
                        ],
                    ],
                ],
            ],
            $result
        );
    }

    public function testBuildSkipsUnknownBrandAndNonArrayRules(): void
    {
        $this->order->method('getGrandTotalAmount')->willReturn(200.0);

        $this->configHelper
            ->method('getAdyenCcConfigData')
            ->willReturnMap([
                ['enable_installments', 1, true],
                ['installments', 1, 'raw'],
            ]);

        $this->adyenHelper
            ->method('getCcTypesAltData')
            ->willReturn([
                'visa' => ['code' => 'visa'],
            ]);

        $this->serializer
            ->method('unserialize')
            ->willReturn([
                'unknown_brand' => [20 => [2, 3]],
                'visa' => 'not-an-array',
            ]);

        $result = $this->subject->build([
            'payment' => $this->paymentDataObject
        ]);

        $this->assertSame([], $result);
    }

    public function testBuildSupportsVariousInstallmentValueShapes(): void
    {
        $this->order->method('getGrandTotalAmount')->willReturn(250.0);

        $this->configHelper
            ->method('getAdyenCcConfigData')
            ->willReturnMap([
                ['enable_installments', 1, true],
                ['installments', 1, 'raw'],
            ]);

        $this->adyenHelper
            ->method('getCcTypesAltData')
            ->willReturn([
                'mc' => ['code' => 'mc'],
            ]);

        $this->serializer
            ->method('unserialize')
            ->willReturn([
                'mc' => [
                    // scalar
                    20 => 3,
                    // array with numeric + non-numeric
                    60 => [2, 'x', 4],
                    // nested "values" shape
                    100 => ['values' => [6, 8]],
                    // empty should be ignored
                    120 => [],
                ],
            ]);

        $result = $this->subject->build([
            'payment' => $this->paymentDataObject
        ]);

        $this->assertSame(
            [
                'body' => [
                    'installmentOptions' => [
                        'mc' => [
                            // eligible tiers are all (orderAmount=250)
                            // values merged: [3] + [2,4] + [6,8] + 1, sorted unique
                            'values' => [1, 2, 3, 4, 6, 8],
                        ],
                    ],
                ],
            ],
            $result
        );
    }

    public function testBuildBreaksEarlyWhenOrderAmountBelowFirstThresholdAndReturnsOnlyOne(): void
    {
        // orderAmount is below the first minAmount (20)
        $this->order->method('getGrandTotalAmount')->willReturn(10.0);

        $this->configHelper
            ->method('getAdyenCcConfigData')
            ->willReturnMap([
                ['enable_installments', 1, true],
                ['installments', 1, 'raw'],
            ]);

        $this->adyenHelper
            ->method('getCcTypesAltData')
            ->willReturn([
                'visa' => ['code' => 'visa'],
            ]);

        $this->serializer
            ->method('unserialize')
            ->willReturn([
                'visa' => [
                    20 => [2, 3],
                    60 => [1],
                ],
            ]);

        $result = $this->subject->build([
            'payment' => $this->paymentDataObject
        ]);

        $this->assertSame(
            [
                'body' => [
                    'installmentOptions' => [
                        'visa' => [
                            'values' => [1],
                        ],
                    ],
                ],
            ],
            $result
        );
    }
}

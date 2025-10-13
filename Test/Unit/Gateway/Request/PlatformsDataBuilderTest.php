<?php

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\PlatformsDataBuilder;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class PlatformsDataBuilderTest extends AbstractAdyenTestCase
{
    protected ?PlatformsDataBuilder $platformsDataBuilder;
    protected Config|MockObject $configMock;
    protected PaymentDataObject|MockObject $paymentDataObjectMock;

    const STORE_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $orderMock = $this->createMock(Order::class);
        $orderMock->expects($this->once())->method('getStoreId')->willReturn(self::STORE_ID);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->expects($this->once())->method('getOrder')->willReturn($orderMock);

        $this->paymentDataObjectMock = $this->createConfiguredMock(PaymentDataObject::class, [
            'getPayment' => $paymentMock
        ]);

        $this->configMock = $this->createMock(Config::class);
        $this->platformsDataBuilder = new PlatformsDataBuilder($this->configMock);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->platformsDataBuilder = null;
    }

    public function testBuildWithStore(): void
    {
        $platformsStore = 'MOCK_AFP_STORE';

        $buildSubject = [
            'payment' => $this->paymentDataObjectMock
        ];

        $this->configMock->expects($this->once())
            ->method('getPlatformsStore')
            ->with(self::STORE_ID)
            ->willReturn($platformsStore);

        $result = $this->platformsDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('store', $result['body']);
        $this->assertEquals($platformsStore, $result['body']['store']);
    }

    public function testBuildWithoutStore(): void
    {
        $buildSubject = [
            'payment' => $this->paymentDataObjectMock
        ];

        $this->configMock->expects($this->once())
            ->method('getPlatformsStore')
            ->with(self::STORE_ID)
            ->willReturn(null);

        $result = $this->platformsDataBuilder->build($buildSubject);

        $this->assertArrayNotHasKey('body', $result);
    }
}

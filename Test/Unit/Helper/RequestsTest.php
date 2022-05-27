<?php declare(strict_types=1);

namespace Adyen\Payment\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class RequestsTest extends TestCase
{
    private $sut;
    private $paymentMock;

    public function testGuestNoStorePaymentMethod()
    {
        $this->setMockObjects([]);
        $this->assertEmpty($this->sut->buildCardRecurringData(1, $this->paymentMock));
    }

    public function testStorePaymentMethodFalse()
    {
        $this->setMockObjects(['storePaymentMethod' => false]);
        $storePaymentMethodValue = $this->sut->buildCardRecurringData(1, $this->paymentMock)['storePaymentMethod'];

        $this->assertFalse($storePaymentMethodValue);
    }

    public function testStorePaymentMethodTrue()
    {
        $this->setMockObjects(['storePaymentMethod' => true]);
        $storePaymentMethodValue = $this->sut->buildCardRecurringData(1, $this->paymentMock)['storePaymentMethod'];

        $this->assertTrue($storePaymentMethodValue);
    }

    private function setMockObjects(array $stateDataArray): void
    {
        // Model\Order\Payment\Interceptor
        $stateDataMock = $this->createMock(StateData::class);
        $stateDataMock->method('getStateData')->willReturn($stateDataArray);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getQuoteId')->willReturn(1);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->paymentMock->method('getOrder')->willReturn($orderMock);

        $this->sut = new Requests(
            $this->createMock(Data::class),
            $this->createMock(Config::class),
            $this->createMock(Address::class),
            $stateDataMock,
            $this->createMock(PaymentMethods::class),
            $this->createMock(Vault::class)
        );
    }
}

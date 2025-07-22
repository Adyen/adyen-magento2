<?php

namespace Test\Unit\Gateway\Response;

use Adyen\Payment\Gateway\Response\AbstractOrderStatusHistoryHandler;
use Adyen\Payment\Helper\OrderStatusHistory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractOrderStatusHistoryHandlerTest extends AbstractAdyenTestCase
{
    protected ?AbstractOrderStatusHistoryHandler $orderStatusHistoryHandler;
    protected OrderStatusHistory|MockObject $orderStatusHistoryMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected ?string $actionDescription = null;
    protected ?string $apiEndpoint = null;

    /**
     * @return void
     */
    public function generateSut(): void
    {
        $this->orderStatusHistoryHandler = new AbstractOrderStatusHistoryHandler(
            $this->actionDescription,
            $this->apiEndpoint,
            $this->orderStatusHistoryMock,
            $this->adyenLoggerMock
        );
    }

    protected function setUp(): void
    {
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->orderStatusHistoryMock = $this->createMock(OrderStatusHistory::class);
    }

    protected function tearDown(): void
    {
        $this->orderStatusHistoryHandler = null;
    }

    public function testMissingArguments(): void
    {
        $this->apiEndpoint = '';
        $this->actionDescription = '';

        $this->generateSut();

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with('Order status history can not be handled due to missing constructor arguments!');

        $handlingSubject = [];
        $responseCollection = [];

        $this->orderStatusHistoryHandler->handle($handlingSubject, $responseCollection);
    }

    public function testHandleSuccess(): void
    {
        $this->actionDescription = 'Send Adyen payment details';
        $this->apiEndpoint = '/payments/details';

        $this->generateSut();

        $orderMock = $this->createMock(Order::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->expects($this->any())->method('getOrder')->willReturn($orderMock);

        $infoInstance = $this->createMock(PaymentDataObjectInterface::class);
        $infoInstance->method('getPayment')->willReturn($paymentMock);

        $handlingSubject = ['payment' => $infoInstance];

        $responseCollection = [
            [
                'resultCode' => 'Authorized',
                'pspReference' => 'XYZ123456'
            ],
            [
                'resultCode' => 'Authorized',
                'pspReference' => 'ABC123456'
            ]
        ];

        $this->orderStatusHistoryMock->expects($this->exactly(2))->method('buildApiResponseComment');
        $this->orderStatusHistoryHandler->handle($handlingSubject, $responseCollection);
    }
}

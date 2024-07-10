<?php

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Adyen\Payment\Model\Api\AdyenPosCloud;

class AdyenPosCloudTest extends AbstractAdyenTestCase
{
    private CommandPoolInterface $commandPoolMock;
    protected AdyenLogger $adyenLoggerMock;
    protected OrderRepository $orderRepositoryMock;
    protected PaymentDataObjectFactoryInterface $paymentDataObjectFactoryMock;
    protected AdyenPosCloud $adyenPosCloud;

    protected function setUp(): void
    {
        $this->commandPoolMock = $this->createMock(CommandPoolInterface::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->paymentDataObjectFactoryMock = $this->createMock(PaymentDataObjectFactoryInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenPosCloud = new AdyenPosCloud(
            $this->commandPoolMock,
            $this->orderRepositoryMock,
            $this->paymentDataObjectFactoryMock,
            $this->adyenLoggerMock
        );
    }

    public function testPaySuccessfully()
    {
        $orderId = 123;
        $orderMock = $this->createMock(OrderInterface::class);
        $this->orderRepositoryMock->method('get')->willReturn($orderMock);
        $paymentMock = $this->createMock(InfoInterface::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $paymentDataObject = $this->createMock(PaymentDataObject::class);
        $this->paymentDataObjectFactoryMock->method('create')
            ->with($paymentMock)->willReturn($paymentDataObject);
        $command = $this->createMock(CommandInterface::class);
        $command->expects($this->once())->method('execute')->with(['payment' => $paymentDataObject]);
        $this->commandPoolMock->method('get')->with('authorize')->willReturn($command);

        $this->adyenPosCloud->pay($orderId, '{}');
    }
}

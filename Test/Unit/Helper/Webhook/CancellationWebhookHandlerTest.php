<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Api\CleanupAdditionalInformationInterface;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\Webhook\CancellationWebhookHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\PaymentStates;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Sales\Model\Order as MagentoOrder;

class CancellationWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected ?CancellationWebhookHandler $cancellationWebhookHandler;
    protected Order|MockObject $orderHelperMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected CleanupAdditionalInformationInterface|MockObject $cleanupAdditionalInformationMock;

    protected function setUp(): void
    {
        $this->orderHelperMock = $this->createMock(Order::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->cleanupAdditionalInformationMock = $this->createMock(CleanupAdditionalInformationInterface::class);

        $this->cancellationWebhookHandler = new CancellationWebhookHandler(
            $this->orderHelperMock,
            $this->adyenLoggerMock,
            $this->cleanupAdditionalInformationMock
        );
    }

    protected function tearDown(): void
    {
        $this->cancellationWebhookHandler = null;
    }

    public function testHandleWebhook(): void
    {
        $paymentMock = $this->createMock(MagentoOrder\Payment::class);

        $orderMock = $this->createMock(MagentoOrder::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->expects($this->once())
            ->method('getInvoiceCollection')
            ->willReturn([]);

        $this->orderHelperMock->expects($this->once())->method('holdCancelOrder')->willReturn($orderMock);

        $notificationMock = $this->createWebhook();
        $transitionState = PaymentStates::STATE_CANCELLED;

        $this->cleanupAdditionalInformationMock->expects($this->once())->method('execute');

        $result = $this->cancellationWebhookHandler->handleWebhook($orderMock, $notificationMock, $transitionState);
        $this->assertInstanceOf(MagentoOrder::class, $result);
    }
}

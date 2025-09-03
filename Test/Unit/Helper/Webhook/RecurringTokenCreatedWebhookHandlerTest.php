<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\Webhook\RecurringTokenCreatedWebhookHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\EventCodes;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class RecurringTokenCreatedWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected ?RecurringTokenCreatedWebhookHandler $handler;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected Vault|MockObject $vaultHelperMock;

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->vaultHelperMock = $this->createMock(Vault::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->handler = new RecurringTokenCreatedWebhookHandler(
            $this->adyenLoggerMock,
            $this->vaultHelperMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->handler = null;
    }

    /**
     * @return void
     * @throws Exception|LocalizedException
     */
    public function testHandleWebhookValidToken()
    {
        $method = 'adyen_cc';

        $methodInstanceMock = $this->createMock(MethodInterface::class);
        $methodInstanceMock->method('getCode')->willReturn($method);
        $methodInstanceMock->method('getStore')->willReturn(1);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $notificationMock = $this->createMock(Notification::class);
        $notificationMock->method('getPspreference')->willReturn('MOCK_TOKEN_REFERENCE');
        $notificationMock->method('getEventCode')->willReturn(EventCodes::RECURRING_TOKEN_DISABLED);

        $this->vaultHelperMock->expects($this->once())
            ->method('getPaymentMethodRecurringActive')
            ->with($method)
            ->willReturn(true);

        $tokenMock = $this->createMock(PaymentTokenInterface::class);

        $this->vaultHelperMock->expects($this->once())->method('createVaultToken')
            ->with($paymentMock, $notificationMock->getPspreference())
            ->willReturn($tokenMock);

        $extensionAttributesMock = $this->createMock(OrderPaymentExtensionInterface::class);
        $extensionAttributesMock->expects($this->once())->method('setVaultPaymentToken')->with($tokenMock);

        $this->vaultHelperMock->expects($this->once())
            ->method('getExtensionAttributes')
            ->with($paymentMock)
            ->willReturn($extensionAttributesMock);

        $result = $this->handler->handleWebhook($orderMock, $notificationMock, '');

        $this->assertInstanceOf(OrderInterface::class, $result);
    }

    /**
     * @return void
     * @throws Exception|LocalizedException
     */
    public function testHandleWebhookFeatureDisabled()
    {
        $method = 'adyen_cc';
        $storeId = 1;

        $methodInstanceMock = $this->createMock(MethodInterface::class);
        $methodInstanceMock->method('getCode')->willReturn($method);
        $methodInstanceMock->method('getStore')->willReturn($storeId);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethodInstance')->willReturn($methodInstanceMock);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $notificationMock = $this->createMock(Notification::class);
        $notificationMock->method('getPspreference')->willReturn('MOCK_TOKEN_REFERENCE');
        $notificationMock->method('getEventCode')->willReturn(EventCodes::RECURRING_TOKEN_DISABLED);

        $this->vaultHelperMock->expects($this->once())
            ->method('getPaymentMethodRecurringActive')
            ->with($method)
            ->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $result = $this->handler->handleWebhook($orderMock, $notificationMock, '');

        $this->assertInstanceOf(OrderInterface::class, $result);
    }
}

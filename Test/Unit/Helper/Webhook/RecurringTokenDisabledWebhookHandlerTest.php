<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\Webhook\RecurringTokenDisabledWebhookHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\EventCodes;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class RecurringTokenDisabledWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected ?RecurringTokenDisabledWebhookHandler $handler;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected PaymentTokenRepositoryInterface|MockObject $paymentTokenRepositoryMock;
    protected Vault|MockObject $vaultHelperMock;

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->vaultHelperMock = $this->createMock(Vault::class);
        $this->paymentTokenRepositoryMock = $this->createMock(PaymentTokenRepositoryInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->handler = new RecurringTokenDisabledWebhookHandler(
            $this->adyenLoggerMock,
            $this->paymentTokenRepositoryMock,
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
     * @throws Exception
     */
    public function testHandleWebhookValidToken()
    {
        $orderMock = $this->createMock(Order::class);
        $notificationMock = $this->createMock(Notification::class);
        $notificationMock->method('getPspreference')->willReturn('MOCK_TOKEN_REFERENCE');
        $notificationMock->method('getEventCode')->willReturn(EventCodes::RECURRING_TOKEN_DISABLED);

        $tokenMock = $this->createMock(PaymentTokenInterface::class);

        $this->vaultHelperMock->expects($this->once())
            ->method('getVaultTokenByStoredPaymentMethodId')
            ->with($notificationMock->getPspreference())
            ->willReturn($tokenMock);

        $tokenMock->expects($this->once())->method('setIsActive')->willReturn(false);
        $tokenMock->expects($this->once())->method('setIsVisible')->willReturn(false);
        $tokenMock->expects($this->once())->method('getEntityId')->willReturn(1);

        $this->paymentTokenRepositoryMock->expects($this->once())->method('save')->with($tokenMock);

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with("Vault payment token with entity_id: 1 disabled due to the failing recurring.token.disabled webhook notification.");

        $result = $this->handler->handleWebhook($orderMock, $notificationMock, '');

        $this->assertInstanceOf(OrderInterface::class, $result);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testHandleWebhookWithoutToken()
    {
        $orderMock = $this->createMock(Order::class);
        $notificationMock = $this->createMock(Notification::class);
        $notificationMock->method('getPspreference')->willReturn('MOCK_TOKEN_REFERENCE');

        $this->paymentTokenRepositoryMock->expects($this->never())->method('save');
        $this->adyenLoggerMock->expects($this->never())->method('addAdyenNotification');

        $result = $this->handler->handleWebhook($orderMock, $notificationMock, '');

        $this->assertInstanceOf(OrderInterface::class, $result);
    }
}

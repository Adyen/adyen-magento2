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

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\Webhook\ExpireWebhookHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

class ExpireWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected ?ExpireWebhookHandler $webhookHandler;
    protected Config|MockObject $configMock;
    protected OrderHelper|MockObject $orderHelperMock;
    protected Data|MockObject $adyenHelperMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected ChargedCurrency|MockObject $chargedCurrencyMock;
    protected Order|MockObject $orderMock;
    protected Notification|MockObject $notificationMock;

    protected function setUp(): void
    {
        // Generic mock items
        $this->orderMock = $this->createMock(Order::class);
        $this->orderMock->method('getStoreId')->willReturn(1);

        $this->notificationMock = $this->createMock(Notification::class);
        $this->notificationMock->method('getEventCode')->willReturn(Notification::EXPIRE);

        // Constructor arguments
        $this->configMock = $this->createMock(Config::class);
        $this->orderHelperMock = $this->createMock(OrderHelper::class);
        $this->adyenHelperMock = $this->createPartialMock(Data::class, []);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);

        $this->webhookHandler = new ExpireWebhookHandler(
            $this->configMock,
            $this->orderHelperMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->chargedCurrencyMock
        );
    }

    protected function tearDown(): void
    {
        $this->webhookHandler = null;
    }

    public function testExpireWebhookIgnored()
    {
        $this->configMock->expects($this->once())
            ->method('isExpireWebhookIgnored')
            ->willReturn(true);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');
        $this->orderMock->expects($this->once())->method('addCommentToStatusHistory');
        $this->orderHelperMock->expects($this->never())->method('holdCancelOrder');

        $result = $this->webhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            'pending'
        );

        $this->assertInstanceOf(OrderInterface::class, $result);
    }

    public function testHandleWebhookWithPartialExpiration()
    {
        $this->configMock->expects($this->once())
            ->method('isExpireWebhookIgnored')
            ->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');
        $this->orderHelperMock->expects($this->never())->method('holdCancelOrder');
        $this->notificationMock->method('getAmountValue')->willReturn(2000);

        $orderAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $orderAmountCurrencyMock->method('getAmount')->willReturn(45.00);
        $orderAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $this->chargedCurrencyMock->expects($this->once())
            ->method('getOrderAmountCurrency')
            ->willReturn($orderAmountCurrencyMock);

        $this->orderMock->expects($this->once())->method('addCommentToStatusHistory');

        $result = $this->webhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            'pending'
        );

        $this->assertInstanceOf(OrderInterface::class, $result);
    }

    public function testHandleWebhookWithShipments()
    {
        $this->configMock->expects($this->once())
            ->method('isExpireWebhookIgnored')
            ->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');
        $this->orderHelperMock->expects($this->never())->method('holdCancelOrder');
        $this->notificationMock->method('getAmountValue')->willReturn(4500);

        $orderAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $orderAmountCurrencyMock->method('getAmount')->willReturn(45.00);
        $orderAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $this->chargedCurrencyMock->expects($this->once())
            ->method('getOrderAmountCurrency')
            ->willReturn($orderAmountCurrencyMock);

        $this->orderMock->expects($this->once())->method('hasShipments')->willReturn(true);
        $this->orderMock->expects($this->once())->method('addCommentToStatusHistory');

        $result = $this->webhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            'pending'
        );

        $this->assertInstanceOf(OrderInterface::class, $result);
    }

    public function testHandleWebhookSuccessfully()
    {
        $this->configMock->expects($this->once())
            ->method('isExpireWebhookIgnored')
            ->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');
        $this->notificationMock->method('getAmountValue')->willReturn(4500);

        $orderAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $orderAmountCurrencyMock->method('getAmount')->willReturn(45.00);
        $orderAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $this->chargedCurrencyMock->expects($this->once())
            ->method('getOrderAmountCurrency')
            ->willReturn($orderAmountCurrencyMock);

        $this->orderMock->expects($this->once())->method('hasShipments')->willReturn(false);
        $this->orderMock->expects($this->once())->method('addCommentToStatusHistory');

        $this->orderHelperMock->expects($this->once())
            ->method('holdCancelOrder')
            ->with($this->orderMock, false)
            ->willReturn($this->orderMock);

        $result = $this->webhookHandler->handleWebhook(
            $this->orderMock,
            $this->notificationMock,
            'pending'
        );

        $this->assertInstanceOf(OrderInterface::class, $result);
    }
}

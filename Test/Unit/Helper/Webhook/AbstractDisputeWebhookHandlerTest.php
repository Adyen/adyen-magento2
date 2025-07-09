<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Webhook\ChargebackReversedWebhookHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\PaymentStates;
use Magento\Sales\Model\Order as MagentoOrder;
use Adyen\Payment\Model\Notification;

class AbstractDisputeWebhookHandlerTest extends AbstractAdyenTestCase
{
    private $orderHelperMock;
    private $adyenLoggerMock;
    private $configHelperMock;
    private $abstractDisputeWebhookHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderHelperMock = $this->createMock(Order::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);

        $this->abstractDisputeWebhookHandler = new ChargebackReversedWebhookHandler(
            $this->orderHelperMock,
            $this->adyenLoggerMock,
            $this->configHelperMock
        );
    }

    public function testIgnoreDisputeNotificationWhenConfigurationIsEnabled()
    {
        $storeId = 1;
        $ignoreDisputeNotifications = true;
        $notificationId = '12345';
        $pspReference = 'test_psp_reference';
        $merchantReference = 'test_merchant_reference';

        $orderMock = $this->createMock(MagentoOrder::class);
        $notificationMock = $this->createMock(Notification::class);

        $orderMock->method('getStoreId')->willReturn($storeId);
        $notificationMock->method('getId')->willReturn($notificationId);
        $notificationMock->method('getPspreference')->willReturn($pspReference);
        $notificationMock->method('getMerchantReference')->willReturn($merchantReference);

        $this->configHelperMock->expects($this->once())
            ->method('getConfigData')
            ->with('ignore_dispute_notification', 'adyen_abstract', $storeId)
            ->willReturn($ignoreDisputeNotifications);

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Config to ignore dispute notification is enabled'),
                $this->equalTo(['pspReference' => $pspReference, 'merchantReference' => $merchantReference])
            );

        $result = $this->abstractDisputeWebhookHandler->handleWebhook($orderMock, $notificationMock, '');

        $this->assertInstanceOf(MagentoOrder::class, $result);
    }
     public function testHandleWebhookWithRefundNotificationAndIgnoreDisputeNotificationsDisabled()
    {
        $storeId = 1;
        $pspReference = 'test_psp_reference';
        $entityId = 'test_entity_id';
        $eventCode = 'REFUND';
        $notificationId = 'test_notification_id';

        $orderMock = $this->createMock(MagentoOrder::class);
        $notificationMock = $this->createMock(Notification::class);
        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);

        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $paymentMock->method('getData')->willReturnMap([
            ['adyen_psp_reference', null, $pspReference],
            ['entity_id', null, $entityId]
        ]);

        $notificationMock->method('getEventCode')->willReturn($eventCode);
        $notificationMock->method('getId')->willReturn($notificationId);

        $this->configHelperMock->method('getConfigData')->with('ignore_dispute_notification', 'adyen_abstract', $storeId)->willReturn(false);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification')->with(
            $this->stringContains('There is a REFUND notification for the order'),
            $this->equalTo(['pspReference' => $pspReference, 'merchantReference' => $entityId])
        );

        $result = $this->abstractDisputeWebhookHandler->handleWebhook($orderMock, $notificationMock, 'REFUND');

        $this->assertEquals($orderMock, $result);
    }
    public function testHandleRefundNotificationWhenDisputeNotificationsAreNotIgnored()
    {
        $storeId = 1;
        $ignoreDisputeNotifications = false;
        $transitionState = PaymentStates::STATE_REFUNDED;
        $eventCode = 'REFUND';
        $pspReference = 'test_psp_reference';
        $merchantReference = 'test_merchant_reference';

        $orderMock = $this->createMock(MagentoOrder::class);
        $notificationMock = $this->createMock(Notification::class);

        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getPayment')->willReturnSelf();
        $orderMock->method('getData')
            ->willReturnMap([
                ['adyen_psp_reference', null, $pspReference],
                ['entity_id', null, $merchantReference]
            ]);

        $notificationMock->method('getEventCode')->willReturn($eventCode);
        $notificationMock->method('getId')->willReturn('1');
        $notificationMock->method('getPspreference')->willReturn($pspReference);
        $notificationMock->method('getMerchantReference')->willReturn($merchantReference);

        $this->configHelperMock->method('getConfigData')->with('ignore_dispute_notification', 'adyen_abstract', $storeId)
            ->willReturn($ignoreDisputeNotifications);

        $this->orderHelperMock->expects($this->once())->method('refundOrder')->with($orderMock, $notificationMock)->willReturn($orderMock);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification')
            ->with($this->stringContains('The order has been updated by the REFUND notification.'), $this->arrayHasKey('pspReference'))
            ->willReturn(true);

        $result = $this->abstractDisputeWebhookHandler->handleWebhook($orderMock, $notificationMock, $transitionState);

        $this->assertEquals($orderMock, $result);
    }
}

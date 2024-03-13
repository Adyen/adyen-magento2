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
}
<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Webhook\RecurringTokenUpdatedWebhookHandler;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class RecurringTokenUpdatedWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected ?RecurringTokenUpdatedWebhookHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new RecurringTokenUpdatedWebhookHandler();
    }

    protected function tearDown(): void
    {
        $this->handler = null;
    }

    public function testHandleWebhook()
    {
        $orderMock = $this->createMock(Order::class);
        $notificationMock = $this->createWebhook();

        $orderMock->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with(__('Recurring token has been updated successfully.'));

        $result = $this->handler->handleWebhook($orderMock, $notificationMock, '');

        $this->assertInstanceOf(OrderInterface::class, $result);
    }
}

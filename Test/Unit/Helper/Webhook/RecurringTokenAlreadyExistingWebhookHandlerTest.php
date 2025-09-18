<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Helper\Webhook\RecurringTokenAlreadyExistingWebhookHandler;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\Exception;

class RecurringTokenAlreadyExistingWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected ?RecurringTokenAlreadyExistingWebhookHandler $handler;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->handler = new RecurringTokenAlreadyExistingWebhookHandler();
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
    public function testHandleWebhook()
    {
        $orderMock = $this->createMock(Order::class);
        $notificationMock = $this->createWebhook();

        $orderMock->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with(__('Recurring token already exists and had been linked to this customer.'));

        $result = $this->handler->handleWebhook($orderMock, $notificationMock, '');

        $this->assertInstanceOf(OrderInterface::class, $result);
    }
}

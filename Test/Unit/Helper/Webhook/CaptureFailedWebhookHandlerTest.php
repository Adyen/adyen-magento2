<?php

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Exception\AdyenWebhookException;
use Adyen\Payment\Helper\Webhook\CaptureFailedWebhookHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

class CaptureFailedWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected ?CaptureFailedWebhookHandler $webhookHandler;
    protected AdyenLogger|MockObject $adyenLoggerMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->webhookHandler = new CaptureFailedWebhookHandler(
            $this->adyenLoggerMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->webhookHandler = null;
    }

    /**
     * @return void
     * @throws AdyenWebhookException
     */
    public function testHandleWebhook(): void
    {
        $pspReference = 'ABC123456';
        $paymentPspReference = 'XYZ123456';
        $reason = 'Capture Failed';
        $merchantReference = '123456';
        $transitionState = 'paid';

        $orderMock = $this->createMock(Order::class);

        $notificationMock = $this->createWebhook($paymentPspReference, $pspReference);
        $notificationMock->method('getReason')->willReturn($reason);
        $notificationMock->method('getMerchantReference')->willReturn($merchantReference);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification')
            ->with(new Phrase("Capture attempt for payment with reference %1 failed. Please visit Customer Area for further details.", [$paymentPspReference]), [
                'capturePspReference' => $pspReference,
                'paymentPspReference' => $paymentPspReference,
                'merchantReference' => $merchantReference
            ]);

        $orderMock->expects($this->once())->method('addCommentToStatusHistory')
            ->with("Capture attempt for payment with reference $paymentPspReference failed. Please visit Customer Area for further details.<br />Reason: $reason");

        $result = $this->webhookHandler->handleWebhook($orderMock, $notificationMock, $transitionState);
        $this->assertInstanceOf(Order::class, $result);
    }
}

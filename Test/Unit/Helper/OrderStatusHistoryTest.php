<?php

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\OrderStatusHistory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

class OrderStatusHistoryTest extends AbstractAdyenTestCase
{
    protected ?OrderStatusHistory $orderStatusHistory;
    protected ChargedCurrency|MockObject $chargedCurrencyMock;
    protected Data|MockObject $adyenHelperMock;

    protected function setUp(): void
    {
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->adyenHelperMock = $this->createMock(Data::class);

        $this->orderStatusHistory = new OrderStatusHistory(
            $this->chargedCurrencyMock,
            $this->adyenHelperMock
        );
    }

    protected function tearDown(): void
    {
        $this->orderStatusHistory = null;
    }

    public function testBuildApiResponseComment(): void
    {
        $mockResponse = [
            'resultCode' => 'Authorized',
            'status' => 'Authorized',
            'pspReference' => 'XYZ123456789',
            'paymentPspReference' => 'XYZ123456789',
            'paymentMethod' => [
                'brand' => 'visa'
            ],
            'refusalReason' => 'Authorized',
            'errorCode' => 'Authorized',
        ];

        $result = $this->orderStatusHistory->buildApiResponseComment(
            $mockResponse,
            'Adyen initiate /payments',
            '/payments'
        );

        $this->assertStringContainsString('Result code', $result);
        $this->assertStringContainsString('Status', $result);
        $this->assertStringContainsString('PSP reference', $result);
        $this->assertStringContainsString('Original PSP reference', $result);
        $this->assertStringContainsString('Payment method', $result);
        $this->assertStringContainsString('Refusal reason', $result);
        $this->assertStringContainsString('Error code', $result);
    }

    public function testBuildWebhookComment(): void
    {
        $webhook = $this->createConfiguredMock(Notification::class, [
            'getEventCode' => 'CAPTURE',
            'getPspreference' => 'XYZ123456789',
            'getOriginalReference' => 'XYZ123456789',
            'getPaymentMethod' => 'visa',
            'getSuccess' => '1',
            'isSuccessful' => true,
            'getReason' => 'Authorized',
            'getAmountValue' => 1000,
            'getAmountCurrency' => 'USD',
            'getFormattedAmountCurrency' => '$10.00'
        ]);

        $reservation = 'ABS123';

        $orderMock = $this->createMock(Order::class);
        $this->adyenHelperMock->method('formatAmount')->willReturn(2000);

        $result = $this->orderStatusHistory->buildWebhookComment($orderMock, $webhook, $reservation);

        $this->assertStringContainsString('partial', $result);
        $this->assertStringContainsString('Original PSP reference', $result);
        $this->assertStringContainsString('Payment method', $result);
        $this->assertStringContainsString('Event status', $result);
        $this->assertStringContainsString('Reason', $result);
        $this->assertStringContainsString('Reservation number', $result);
        $this->assertStringContainsString('Amount', $result);
    }
}

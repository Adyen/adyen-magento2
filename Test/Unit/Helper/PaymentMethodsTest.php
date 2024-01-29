<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;

class PaymentMethodsTest extends AbstractAdyenTestCase
{
    private object $paymentMethodsHelper;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);
        $this->paymentMethodsHelper = $objectManager->getObject(PaymentMethods::class, []);
    }

    /**
     * @dataProvider comparePaymentMethodProvider
     */
    public function testCompareOrderAndWebhookPaymentMethods(
        $orderPaymentMethod,
        $notificationPaymentMethod,
        $assert,
        $ccType = null
    ) {
        $methodMock = $this->createMock(MethodInterface::class);
        $methodMock->method('getConfigData')
            ->willReturnMap([
                ['group', null, PaymentMethods::ADYEN_GROUP_ALTERNATIVE_PAYMENT_METHODS],
                ['is_wallet', null, '0']
            ]);
        $methodMock->method('getCode')->willReturn($orderPaymentMethod);

        $paymentMock = $this->createMock(Order\Payment::class);
        $paymentMock->method('getMethodInstance')->willReturn($methodMock);
        $paymentMock->method('getMethod')->willReturn($orderPaymentMethod);
        $paymentMock->method('getCcType')->willReturn($ccType);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $notificationMock = $this->createMock(Notification::class);
        $notificationMock->method('getPaymentMethod')->willReturn($notificationPaymentMethod);

        $this->assertEquals(
            $assert,
            $this->paymentMethodsHelper->compareOrderAndWebhookPaymentMethods($orderMock, $notificationMock)
        );
    }

    public static function comparePaymentMethodProvider(): array
    {
        return [
            [
                'orderPaymentMethod' => 'adyen_klarna',
                'notificationPaymentMethod' => 'klarna',
                'assert' => true
            ],
            [
                'orderPaymentMethod' => 'adyen_cc',
                'notificationPaymentMethod' => 'visa',
                'assert' => true,
                'ccType' => 'visa'
            ],
            [
                'orderPaymentMethod' => 'adyen_klarna',
                'notificationPaymentMethod' => 'boleto',
                'assert' => false
            ]
        ];
    }
}

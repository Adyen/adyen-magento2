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

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\PaymentsDetails;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\AdyenPaymentsDetails;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class AdyenPaymentsDetailsTest extends AbstractAdyenTestCase
{
    private $adyenPaymentsDetails;
    private $orderRepositoryMock;
    private $paymentsDetailsHelperMock;
    private $paymentResponseHandlerHelperMock;
    private $adyenLoggerMock;

    protected function setUp(): void
    {
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->paymentsDetailsHelperMock = $this->createMock(PaymentsDetails::class);
        $this->paymentResponseHandlerHelperMock = $this->createPartialMock(
            PaymentResponseHandler::class,
            ['handlePaymentsDetailsResponse']
        );
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $objectManager = new ObjectManager($this);
        $this->adyenPaymentsDetails = $objectManager->getObject(AdyenPaymentsDetails::class, [
            'orderRepository' => $this->orderRepositoryMock,
            'paymentsDetails' => $this->paymentsDetailsHelperMock,
            'paymentResponseHandler' => $this->paymentResponseHandlerHelperMock,
            'adyenLogger' => $this->adyenLoggerMock,
        ]);
    }

    public function testSuccessfulCall()
    {
        $payload = '{"someData":"someValue"}';
        $result = ['resultCode' => 'Authorised'];
        $orderId = 1;

        $this->orderRepositoryMock
            ->method('get')
            ->willReturn($this->createMock(OrderInterface::class));

        $this->paymentsDetailsHelperMock
            ->method('initiatePaymentDetails')
            ->willReturn($result);

        $this->paymentResponseHandlerHelperMock
            ->method('handlePaymentsDetailsResponse')
            ->willReturn(true);

        $response = $this->adyenPaymentsDetails->initiate($payload, $orderId);

        $this->assertJson($response);
        $this->assertArrayHasKey('isFinal', json_decode($response, true));
        $this->assertArrayHasKey('resultCode', json_decode($response, true));
    }

    public function testFailingJson()
    {
        $this->expectException(ValidatorException::class);

        $payload = '{"someData":"someValue"';
        $orderId = 1;

        $this->adyenPaymentsDetails->initiate($payload, $orderId);
    }

    public function testInvalidDetailsCall()
    {
        $this->expectException(ValidatorException::class);

        $payload = '{"someData":"someValue"}';
        $result = ['resultCode' => 'Authorised'];
        $orderId = 1;

        $this->orderRepositoryMock
            ->method('get')
            ->willReturn($this->createMock(OrderInterface::class));

        $this->paymentsDetailsHelperMock
            ->method('initiatePaymentDetails')
            ->willReturn($result);

        $this->paymentResponseHandlerHelperMock
            ->method('handlePaymentsDetailsResponse')
            ->willReturn(false);

        $this->adyenPaymentsDetails->initiate($payload, $orderId);
    }


}

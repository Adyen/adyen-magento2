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

use Adyen\Payment\Model\Api\AdyenPaymentsDetails;
use Adyen\Payment\Model\Api\GuestAdyenPaymentsDetails;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class GuestAdyenPaymentsDetailsTest extends AbstractAdyenTestCase
{
    private $guestAdyenPaymentsDetails;
    private $orderRepositoryMock;
    private $quoteIdMaskFactoryMask;
    private $adyenPaymentsDetailsMock;

    protected function setUp(): void
    {
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->adyenPaymentsDetailsMock = $this->createMock(AdyenPaymentsDetails::class);
        $this->quoteIdMaskFactoryMask = $this->createGeneratedMock(QuoteIdMaskFactory::class, [
            'create'
        ]);

        $objectManager = new ObjectManager($this);
        $this->guestAdyenPaymentsDetails = $objectManager->getObject(GuestAdyenPaymentsDetails::class, [
            'orderRepository' => $this->orderRepositoryMock,
            'adyenPaymentsDetails' => $this->adyenPaymentsDetailsMock,
            'quoteIdMaskFactory' => $this->quoteIdMaskFactoryMask
        ]);
    }

    public function testSuccessfulCall()
    {
        $payload = '{"someData":"someValue"}';
        $result = '{"resultCode": "Authorised", "isFinal": "true"}';
        $orderId = 1;
        $maskedCartId = 'abcdef123456';
        $cartId = 99;
        $orderQuoteId = 99;

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, [
            'load',
            'getQuoteId'
        ]);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn($cartId);

        $this->quoteIdMaskFactoryMask->method('create')
            ->willReturn($quoteIdMaskMock);

        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getQuoteId')->willReturn($orderQuoteId);

        $this->orderRepositoryMock->method('get')
            ->willReturn($orderMock);

        $this->adyenPaymentsDetailsMock->method('initiate')
            ->willReturn($result);

        $response = $this->guestAdyenPaymentsDetails->initiate($payload, $orderId, $maskedCartId);

        $this->assertJson($response);
        $this->assertArrayHasKey('isFinal', json_decode($response, true));
        $this->assertArrayHasKey('resultCode', json_decode($response, true));
    }

    public function testWrongCartId()
    {
        $this->expectException(NotFoundException::class);

        $payload = '{"someData":"someValue"}';
        $orderId = 1;
        $maskedCartId = 'abcdef123456';
        $cartId = 99;
        $orderQuoteId = 200;

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, [
            'load',
            'getQuoteId'
        ]);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn($cartId);

        $this->quoteIdMaskFactoryMask->method('create')
            ->willReturn($quoteIdMaskMock);

        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getQuoteId')->willReturn($orderQuoteId);

        $this->orderRepositoryMock->method('get')
            ->willReturn($orderMock);

        $this->guestAdyenPaymentsDetails->initiate($payload, $orderId, $maskedCartId);
    }
}

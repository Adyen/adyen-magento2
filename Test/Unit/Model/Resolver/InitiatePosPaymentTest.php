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
namespace Adyen\Payment\Test\Model\Resolver;

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\GuestAdyenPosCloud;
use Adyen\Payment\Model\Resolver\DataProvider\GetAdyenPaymentStatus;
use Adyen\Payment\Model\Resolver\InitiatePosPayment;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;

class  InitiatePosPaymentTest extends AbstractAdyenTestCase
{
    protected ?InitiatePosPayment $resolver;
    protected Field|MockObject $fieldMock;
    protected GuestAdyenPosCloud|MockObject $guestAdyenPosCloudMock;
    protected ContextInterface|MockObject $contextMock;
    protected ResolveInfo|MockObject $infoMock;
    protected MaskedQuoteIdToQuoteIdInterface|MockObject $maskedQuoteIdToQuoteIdMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected GetAdyenPaymentStatus|MockObject $getAdyenPaymentStatusDataProviderMock;
    protected OrderRepository|MockObject $orderRepositoryMock;

    protected function setUp(): void
    {
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(ContextInterface::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->guestAdyenPosCloudMock = $this->createMock(GuestAdyenPosCloud::class);
        $this->getAdyenPaymentStatusDataProviderMock = $this->createMock(GetAdyenPaymentStatus::class);
        $this->maskedQuoteIdToQuoteIdMock = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->maskedQuoteIdToQuoteIdMock->method('execute')->willReturn(1);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);

        $this->resolver = new InitiatePosPayment(
            $this->adyenLoggerMock,
            $this->guestAdyenPosCloudMock,
            $this->getAdyenPaymentStatusDataProviderMock,
            $this->maskedQuoteIdToQuoteIdMock,
            $this->orderRepositoryMock
        );
    }

    protected function tearDown(): void
    {
        $this->resolver = null;
    }

    public function testResolveEmptyArgument(): void
    {
        $this->expectException(GraphQlInputException::class);

        $args = [
            'cartId' => ''
        ];

        $this->resolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock, null, $args);
    }

    public function testResolveQuoteNotFound(): void
    {
        $this->expectException(GraphQlAdyenException::class);

        $args = [
            'cartId' => 'masked_cart_id_mock'
        ];

        $this->maskedQuoteIdToQuoteIdMock
            ->expects($this->once())
            ->method('execute')
            ->willthrowException(new NoSuchEntityException());

        $this->resolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock, null, $args);
    }

    public function testResolveUnknownError(): void
    {
        $this->expectException(GraphQlAdyenException::class);

        $quoteId = 1;
        $cartId = 'masked_cart_id_mock';

        $args = [
            'cartId' => $cartId
        ];

        $this->maskedQuoteIdToQuoteIdMock
            ->expects($this->once())
            ->method('execute')
            ->with($cartId)
            ->willReturn($quoteId);

        $this->orderRepositoryMock->expects($this->once())
            ->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willThrowException(new NoSuchEntityException());

        $this->resolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock, null, $args);
    }

    public function testResolve(): void
    {
        $quoteId = 1;
        $orderId = "99";
        $cartId = 'masked_cart_id_mock';

        $resultMock = [
            'posPayment' => [
                'isFinal' => true,
                'resultCode' => 'Success'
            ]
        ];

        $args = [
            'cartId' => $cartId
        ];

        $this->maskedQuoteIdToQuoteIdMock
            ->expects($this->once())
            ->method('execute')
            ->with($cartId)
            ->willReturn($quoteId);

        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->expects($this->exactly(2))->method('getEntityId')->willReturn($orderId);

        $this->orderRepositoryMock->expects($this->once())
            ->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn($orderMock);

        $this->guestAdyenPosCloudMock->expects($this->once())
            ->method('pay')
            ->with(intval($orderId));

        $this->getAdyenPaymentStatusDataProviderMock->expects($this->once())
            ->method('getGetAdyenPaymentStatus')
            ->with($orderId)
            ->willReturn($resultMock);

        $result = $this->resolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock, null, $args);

        $this->assertArrayHasKey('posPayment', $result);
        $this->assertArrayHasKey('isFinal', $result['posPayment']);
        $this->assertArrayHasKey('resultCode', $result['posPayment']);
    }
}

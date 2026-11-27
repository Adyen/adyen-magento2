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
use Adyen\Payment\Helper\Quote as QuoteHelper;
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
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class  InitiatePosPaymentTest extends AbstractAdyenTestCase
{
    protected ?InitiatePosPayment $resolver;
    protected Field|MockObject $fieldMock;
    protected GuestAdyenPosCloud|MockObject $guestAdyenPosCloudMock;
    protected ContextInterface|MockObject $contextMock;
    protected ResolveInfo|MockObject $infoMock;
    protected QuoteHelper|MockObject $quoteHelperMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected GetAdyenPaymentStatus|MockObject $getAdyenPaymentStatusDataProviderMock;
    protected OrderRepository|MockObject $orderRepositoryMock;

    protected function setUp(): void
    {
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createGraphQlContextMock();
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->guestAdyenPosCloudMock = $this->createMock(GuestAdyenPosCloud::class);
        $this->getAdyenPaymentStatusDataProviderMock = $this->createMock(GetAdyenPaymentStatus::class);
        $this->quoteHelperMock = $this->createMock(QuoteHelper::class);
        $this->quoteHelperMock->method('getCartForUser')->willReturn($this->createQuoteMockWithId(1));
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);

        $this->resolver = new InitiatePosPayment(
            $this->adyenLoggerMock,
            $this->guestAdyenPosCloudMock,
            $this->getAdyenPaymentStatusDataProviderMock,
            $this->quoteHelperMock,
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

        $this->quoteHelperMock
            ->expects($this->once())
            ->method('getCartForUser')
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

        $this->quoteHelperMock
            ->expects($this->once())
            ->method('getCartForUser')
            ->with($cartId)
            ->willReturn($this->createQuoteMockWithId($quoteId));

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

        $this->quoteHelperMock
            ->expects($this->once())
            ->method('getCartForUser')
            ->with($cartId)
            ->willReturn($this->createQuoteMockWithId($quoteId));

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

    public function testResolveRejectsCartNotOwnedByCurrentUser(): void
    {
        $contextMock = $this->createGraphQlContextMock(42, 3);

        // The user ID and store ID from the GraphQL context must be used to resolve the cart
        $this->quoteHelperMock->expects($this->once())
            ->method('getCartForUser')
            ->with('other_customer_cart', 42, 3)
            ->willThrowException(new NoSuchEntityException(
                __('The current user cannot perform operations on cart "%masked_cart_id"', ['masked_cart_id' => 'other_customer_cart'])
            ));
        $this->orderRepositoryMock->expects($this->never())->method('getOrderByQuoteId');
        $this->guestAdyenPosCloudMock->expects($this->never())->method('pay');

        $this->expectException(GraphQlAdyenException::class);

        $this->resolver->resolve(
            $this->fieldMock,
            $contextMock,
            $this->infoMock,
            null,
            ['cartId' => 'other_customer_cart']
        );
    }

    public function testResolveThrowsExceptionWhenNoOrderExistsForCart(): void
    {
        $this->orderRepositoryMock->expects($this->once())
            ->method('getOrderByQuoteId')
            ->with(1)
            ->willReturn(false);

        $this->guestAdyenPosCloudMock->expects($this->never())->method('pay');

        $this->expectException(GraphQlAdyenException::class);

        $this->resolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            ['cartId' => 'masked_cart_id']
        );
    }
}

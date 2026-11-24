<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2026 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Resolver;

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Helper\Quote as QuoteHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\AdyenDonations;
use Adyen\Payment\Model\GraphqlInputArgumentValidator;
use Adyen\Payment\Model\Resolver\Donations;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\GraphQl\Helper\Error\AggregateExceptionMessageFormatter;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class DonationsTest extends AbstractAdyenTestCase
{
    private MockObject $adyenDonationsMock;
    private MockObject $quoteHelperMock;
    private MockObject $orderRepositoryMock;
    private MockObject $jsonSerializerMock;
    private MockObject $graphqlInputArgumentValidatorMock;
    private MockObject $adyenLoggerMock;
    private MockObject $adyenGraphQlExceptionMessageFormatterMock;
    private MockObject $fieldMock;
    private MockObject $contextMock;
    private MockObject $infoMock;
    private Donations $donationsResolver;

    protected function setUp(): void
    {
        $this->adyenDonationsMock = $this->createMock(AdyenDonations::class);
        $this->quoteHelperMock = $this->createMock(QuoteHelper::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->jsonSerializerMock = $this->createMock(Json::class);
        $this->graphqlInputArgumentValidatorMock = $this->createMock(GraphqlInputArgumentValidator::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenGraphQlExceptionMessageFormatterMock = $this->createMock(AggregateExceptionMessageFormatter::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createGraphQlContextMock();
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        $objectManagerMock->method('get')->willReturnMap([
            [Json::class, $this->jsonSerializerMock],
            [AdyenDonations::class, $this->adyenDonationsMock]
        ]);
        ObjectManager::setInstance($objectManagerMock);

        $this->donationsResolver = new Donations(
            $this->quoteHelperMock,
            $this->orderRepositoryMock,
            $this->graphqlInputArgumentValidatorMock,
            $this->adyenLoggerMock,
            $this->adyenGraphQlExceptionMessageFormatterMock
        );
    }

    public function testResolveSuccessful(): void
    {
        $args = [
            'cartId' => 'masked_cart_id',
            'amount' => [
                'currency' => 'EUR',
                'value' => 500
            ],
            'returnUrl' => 'https://example.com/return'
        ];

        $quoteId = 123;
        $orderMock = $this->createMock(Order::class);
        $serializedPayload = '{"amount":{"currency":"EUR","value":500},"returnUrl":"https:\/\/example.com\/return"}';

        $this->quoteHelperMock->method('getCartForUser')
            ->with('masked_cart_id')
            ->willReturn($this->createQuoteMockWithId($quoteId));

        $this->orderRepositoryMock->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn($orderMock);

        $this->jsonSerializerMock->method('serialize')
            ->willReturn($serializedPayload);

        $this->adyenDonationsMock->expects($this->once())
            ->method('makeDonation')
            ->with($serializedPayload, $orderMock);

        $result = $this->donationsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );

        $this->assertEquals(['status' => true], $result);
    }

    public function testResolveThrowsExceptionForMissingRequiredFields(): void
    {
        $args = [
            'cartId' => 'masked_cart_id'
        ];

        $this->graphqlInputArgumentValidatorMock->method('execute')
            ->willThrowException(new GraphQlInputException(__('Required parameters "amount, returnUrl" are missing')));

        $this->expectException(GraphQlInputException::class);

        $this->donationsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveThrowsExceptionWhenQuoteNotFound(): void
    {
        $args = [
            'cartId' => 'invalid_masked_id',
            'amount' => [
                'currency' => 'EUR',
                'value' => 500
            ],
            'returnUrl' => 'https://example.com/return'
        ];

        $this->quoteHelperMock->method('getCartForUser')
            ->with('invalid_masked_id')
            ->willThrowException(new NoSuchEntityException());

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $this->expectException(GraphQlAdyenException::class);

        $this->donationsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveThrowsExceptionWhenOrderNotFound(): void
    {
        $args = [
            'cartId' => 'masked_cart_id',
            'amount' => [
                'currency' => 'EUR',
                'value' => 500
            ],
            'returnUrl' => 'https://example.com/return'
        ];

        $quoteId = 123;

        $this->quoteHelperMock->method('getCartForUser')
            ->with('masked_cart_id')
            ->willReturn($this->createQuoteMockWithId($quoteId));

        $this->orderRepositoryMock->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn(null);

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $this->expectException(GraphQlAdyenException::class);

        $this->donationsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveThrowsFormattedExceptionOnLocalizedException(): void
    {
        $args = [
            'cartId' => 'masked_cart_id',
            'amount' => [
                'currency' => 'EUR',
                'value' => 500
            ],
            'returnUrl' => 'https://example.com/return'
        ];

        $quoteId = 123;
        $orderMock = $this->createMock(Order::class);

        $this->quoteHelperMock->method('getCartForUser')
            ->with('masked_cart_id')
            ->willReturn($this->createQuoteMockWithId($quoteId));

        $this->orderRepositoryMock->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn($orderMock);

        $this->jsonSerializerMock->method('serialize')
            ->willReturn('{}');

        $localizedException = new LocalizedException(__('Donation failed!'));

        $this->adyenDonationsMock->method('makeDonation')
            ->willThrowException($localizedException);

        $formattedException = new GraphQlInputException(__('An error occurred while processing the donation.'));

        $this->adyenGraphQlExceptionMessageFormatterMock
            ->expects($this->once())
            ->method('getFormatted')
            ->with(
                $this->equalTo($localizedException),
                $this->anything(),
                $this->equalTo('An error occurred while processing the donation.'),
                $this->equalTo($this->fieldMock),
                $this->equalTo($this->contextMock),
                $this->equalTo($this->infoMock)
            )
            ->willReturn($formattedException);

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('An error occurred while processing the donation.');

        $this->donationsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveThrowsAdyenExceptionOnGenericException(): void
    {
        $args = [
            'cartId' => 'masked_cart_id',
            'amount' => [
                'currency' => 'EUR',
                'value' => 500
            ],
            'returnUrl' => 'https://example.com/return'
        ];

        $quoteId = 123;
        $orderMock = $this->createMock(Order::class);

        $this->quoteHelperMock->method('getCartForUser')
            ->with('masked_cart_id')
            ->willReturn($this->createQuoteMockWithId($quoteId));

        $this->orderRepositoryMock->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn($orderMock);

        $this->jsonSerializerMock->method('serialize')
            ->willReturn('{}');

        $this->adyenDonationsMock->method('makeDonation')
            ->willThrowException(new Exception('Unexpected error'));

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $this->expectException(GraphQlAdyenException::class);

        $this->donationsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
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
        $this->adyenDonationsMock->expects($this->never())->method('makeDonation');

        $this->expectException(GraphQlAdyenException::class);

        $this->donationsResolver->resolve(
            $this->fieldMock,
            $contextMock,
            $this->infoMock,
            null,
            [
                'cartId' => 'other_customer_cart',
                'amount' => ['currency' => 'EUR', 'value' => 500],
                'returnUrl' => 'https://example.com/return'
            ]
        );
    }
}

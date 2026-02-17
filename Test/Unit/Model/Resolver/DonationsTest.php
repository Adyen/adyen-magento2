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
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\AdyenDonations;
use Adyen\Payment\Model\GraphqlInputArgumentValidator;
use Adyen\Payment\Model\Resolver\Donations;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\GraphQl\Helper\Error\AggregateExceptionMessageFormatter;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

class DonationsTest extends AbstractAdyenTestCase
{
    private MockObject $adyenDonationsMock;
    private MockObject $maskedQuoteIdToQuoteIdMock;
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
        $this->maskedQuoteIdToQuoteIdMock = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->jsonSerializerMock = $this->createMock(Json::class);
        $this->graphqlInputArgumentValidatorMock = $this->createMock(GraphqlInputArgumentValidator::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenGraphQlExceptionMessageFormatterMock = $this->createMock(AggregateExceptionMessageFormatter::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(ContextInterface::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $this->donationsResolver = new Donations(
            $this->adyenDonationsMock,
            $this->maskedQuoteIdToQuoteIdMock,
            $this->orderRepositoryMock,
            $this->jsonSerializerMock,
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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
            ->with('masked_cart_id')
            ->willReturn($quoteId);

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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
            ->with('masked_cart_id')
            ->willReturn($quoteId);

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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
            ->with('masked_cart_id')
            ->willReturn($quoteId);

        $this->orderRepositoryMock->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn($orderMock);

        $this->jsonSerializerMock->method('serialize')
            ->willReturn('{}');

        $localizedException = new LocalizedException(__('Donation failed!'));

        $this->adyenDonationsMock->method('makeDonation')
            ->willThrowException($localizedException);

        $formattedException = new GraphQlInputException(__('Donation failed!'));

        $this->adyenGraphQlExceptionMessageFormatterMock
            ->expects($this->once())
            ->method('getFormatted')
            ->with(
                $this->equalTo($localizedException),
                $this->anything(),
                $this->equalTo('Unable to donate'),
                $this->equalTo($this->fieldMock),
                $this->equalTo($this->contextMock),
                $this->equalTo($this->infoMock)
            )
            ->willReturn($formattedException);

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Donation failed!');

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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
            ->with('masked_cart_id')
            ->willReturn($quoteId);

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
}

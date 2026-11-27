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
use Adyen\Payment\Model\Api\AdyenDonationCampaigns;
use Adyen\Payment\Model\GraphqlInputArgumentValidator;
use Adyen\Payment\Model\Resolver\DonationCampaigns;
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
use Magento\GraphQl\Helper\Error\AggregateExceptionMessageFormatter;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class DonationCampaignsTest extends AbstractAdyenTestCase
{
    private MockObject $adyenDonationCampaignsMock;
    private MockObject $quoteHelperMock;
    private MockObject $orderRepositoryMock;
    private MockObject $graphqlInputArgumentValidatorMock;
    private MockObject $adyenLoggerMock;
    private MockObject $adyenGraphQlExceptionMessageFormatterMock;
    private MockObject $fieldMock;
    private MockObject $contextMock;
    private MockObject $infoMock;
    private DonationCampaigns $donationCampaignsResolver;

    protected function setUp(): void
    {
        $this->adyenDonationCampaignsMock = $this->createMock(AdyenDonationCampaigns::class);
        $this->quoteHelperMock = $this->createMock(QuoteHelper::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->graphqlInputArgumentValidatorMock = $this->createMock(GraphqlInputArgumentValidator::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenGraphQlExceptionMessageFormatterMock = $this->createMock(AggregateExceptionMessageFormatter::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createGraphQlContextMock();
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        $objectManagerMock->method('get')->willReturnMap([
            [AdyenDonationCampaigns::class, $this->adyenDonationCampaignsMock]
        ]);
        ObjectManager::setInstance($objectManagerMock);

        $this->donationCampaignsResolver = new DonationCampaigns(
            $this->quoteHelperMock,
            $this->orderRepositoryMock,
            $this->graphqlInputArgumentValidatorMock,
            $this->adyenLoggerMock,
            $this->adyenGraphQlExceptionMessageFormatterMock
        );
    }

    public function testResolveSuccessful(): void
    {
        $args = ['cartId' => 'masked_cart_id'];
        $quoteId = 123;
        $orderId = 456;
        $campaignsResponse = '{"campaigns": [{"id": "1"}]}';

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);

        $this->quoteHelperMock->method('getCartForUser')
            ->with('masked_cart_id')
            ->willReturn($this->createQuoteMockWithId($quoteId));

        $this->orderRepositoryMock->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn($orderMock);

        $this->adyenDonationCampaignsMock->expects($this->once())
            ->method('getCampaigns')
            ->with($orderId)
            ->willReturn($campaignsResponse);

        $result = $this->donationCampaignsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );

        $this->assertEquals(['campaignsData' => $campaignsResponse], $result);
    }

    public function testResolveThrowsExceptionForMissingRequiredFields(): void
    {
        $args = [];

        $this->graphqlInputArgumentValidatorMock->method('execute')
            ->willThrowException(new GraphQlInputException(__('Required parameters "cartId" are missing')));

        $this->expectException(GraphQlInputException::class);

        $this->donationCampaignsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveThrowsExceptionWhenQuoteNotFound(): void
    {
        $args = ['cartId' => 'invalid_masked_id'];

        $this->quoteHelperMock->method('getCartForUser')
            ->with('invalid_masked_id')
            ->willThrowException(new NoSuchEntityException());

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $this->expectException(GraphQlAdyenException::class);

        $this->donationCampaignsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveThrowsExceptionWhenOrderNotFound(): void
    {
        $args = ['cartId' => 'masked_cart_id'];
        $quoteId = 123;

        $this->quoteHelperMock->method('getCartForUser')
            ->with('masked_cart_id')
            ->willReturn($this->createQuoteMockWithId($quoteId));

        $this->orderRepositoryMock->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn(null);

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $this->expectException(GraphQlAdyenException::class);

        $this->donationCampaignsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveThrowsFormattedExceptionOnLocalizedException(): void
    {
        $args = ['cartId' => 'masked_cart_id'];
        $quoteId = 123;
        $orderId = 456;

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);

        $this->quoteHelperMock->method('getCartForUser')
            ->with('masked_cart_id')
            ->willReturn($this->createQuoteMockWithId($quoteId));

        $this->orderRepositoryMock->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn($orderMock);

        $localizedException = new LocalizedException(__('Unable to retrieve donation campaigns.'));

        $this->adyenDonationCampaignsMock->method('getCampaigns')
            ->with($orderId)
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

        $this->donationCampaignsResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveThrowsAdyenExceptionOnGenericException(): void
    {
        $args = ['cartId' => 'masked_cart_id'];
        $quoteId = 123;
        $orderId = 456;

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getEntityId')->willReturn($orderId);

        $this->quoteHelperMock->method('getCartForUser')
            ->with('masked_cart_id')
            ->willReturn($this->createQuoteMockWithId($quoteId));

        $this->orderRepositoryMock->method('getOrderByQuoteId')
            ->with($quoteId)
            ->willReturn($orderMock);

        $this->adyenDonationCampaignsMock->method('getCampaigns')
            ->with($orderId)
            ->willThrowException(new Exception('Unexpected error'));

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $this->expectException(GraphQlAdyenException::class);

        $this->donationCampaignsResolver->resolve(
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

        $this->expectException(GraphQlAdyenException::class);

        $this->donationCampaignsResolver->resolve(
            $this->fieldMock,
            $contextMock,
            $this->infoMock,
            null,
            ['cartId' => 'other_customer_cart']
        );
    }
}

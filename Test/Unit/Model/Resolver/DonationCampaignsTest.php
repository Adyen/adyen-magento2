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
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

class DonationCampaignsTest extends AbstractAdyenTestCase
{
    private MockObject $adyenDonationCampaignsMock;
    private MockObject $maskedQuoteIdToQuoteIdMock;
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
        $this->maskedQuoteIdToQuoteIdMock = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->graphqlInputArgumentValidatorMock = $this->createMock(GraphqlInputArgumentValidator::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenGraphQlExceptionMessageFormatterMock = $this->createMock(AggregateExceptionMessageFormatter::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(ContextInterface::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        $objectManagerMock->method('get')->willReturnMap([
            [AdyenDonationCampaigns::class, $this->adyenDonationCampaignsMock]
        ]);
        ObjectManager::setInstance($objectManagerMock);

        $this->donationCampaignsResolver = new DonationCampaigns(
            $this->maskedQuoteIdToQuoteIdMock,
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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
            ->with('masked_cart_id')
            ->willReturn($quoteId);

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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
            ->with('masked_cart_id')
            ->willReturn($quoteId);

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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
            ->with('masked_cart_id')
            ->willReturn($quoteId);

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

        $this->maskedQuoteIdToQuoteIdMock->method('execute')
            ->with('masked_cart_id')
            ->willReturn($quoteId);

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
}

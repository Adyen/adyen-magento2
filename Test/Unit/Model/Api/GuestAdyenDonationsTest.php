<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\AdyenException;
use Adyen\Payment\Model\Api\AdyenDonations;
use Adyen\Payment\Model\Api\GuestAdyenDonations;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\Data\OrderInterface;

class GuestAdyenDonationsTest extends AbstractAdyenTestCase
{
    public function testFailingDonation()
    {
        $this->expectException(AdyenException::class);

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $quoteIdMaskMock->method('load')
            ->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')
            ->willReturn(1);

        $quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, ['create']);
        $quoteIdMaskFactoryMock->method('create')
            ->willReturn($quoteIdMaskMock);

        $adyenDonationsModelMock = $this->createPartialMock(AdyenDonations::class, []);

        $orderRepositoryMock = $this->createMock(OrderRepository::class);

        $guestAdyenDonations = new GuestAdyenDonations(
            $adyenDonationsModelMock,
            $quoteIdMaskFactoryMock,
            $orderRepositoryMock
        );

        $guestAdyenDonations->donate(1, '');
    }

    public function testSuccessfulDonation()
    {
        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $quoteIdMaskMock->method('load')
            ->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')
            ->willReturn(1);

        $quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, ['create']);
        $quoteIdMaskFactoryMock->expects(self::atLeastOnce())
            ->method('create')
            ->willReturn($quoteIdMaskMock);

        $adyenDonationsModelMock = $this->createMock(AdyenDonations::class);

        $orderRepositoryMock = $this->createConfiguredMock(OrderRepository::class, [
            'getOrderByQuoteId' => $this->createMock(OrderInterface::class)
        ]);

        $guestAdyenDonations = new GuestAdyenDonations(
            $adyenDonationsModelMock,
            $quoteIdMaskFactoryMock,
            $orderRepositoryMock
        );

        $guestAdyenDonations->donate(1, '');
    }
}

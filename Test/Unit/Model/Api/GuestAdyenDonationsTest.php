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
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\AdyenDonations;
use Adyen\Payment\Model\Api\GuestAdyenDonations;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Model\Order;

class GuestAdyenDonationsTest extends AbstractAdyenTestCase
{
    public function testFailingDonation()
    {
        $this->expectException(AdyenException::class);

        $maskedQuoteIdToQuoteIdMock = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $maskedQuoteIdToQuoteIdMock->expects($this->once())->method('execute')->willReturn(1);

        $adyenDonationsModelMock = $this->createPartialMock(AdyenDonations::class, []);

        $orderRepositoryMock = $this->createMock(OrderRepository::class);

        $adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $guestAdyenDonations = new GuestAdyenDonations(
            $adyenDonationsModelMock,
            $orderRepositoryMock,
            $maskedQuoteIdToQuoteIdMock,
            $adyenLoggerMock
        );

        $guestAdyenDonations->donate(1, '');
    }

    public function testSuccessfulDonation()
    {
        $maskedQuoteIdToQuoteIdMock = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $maskedQuoteIdToQuoteIdMock->expects($this->once())->method('execute')->willReturn(1);

        $adyenDonationsModelMock = $this->createMock(AdyenDonations::class);

        $orderRepositoryMock = $this->createConfiguredMock(OrderRepository::class, [
            'getOrderByQuoteId' => $this->createMock(Order::class)
        ]);

        $adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $guestAdyenDonations = new GuestAdyenDonations(
            $adyenDonationsModelMock,
            $orderRepositoryMock,
            $maskedQuoteIdToQuoteIdMock,
            $adyenLoggerMock
        );

        $guestAdyenDonations->donate(1, '');
    }
}

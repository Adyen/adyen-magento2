<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\AdyenException;
use Adyen\Payment\Api\GuestAdyenDonationsInterface;
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestAdyenDonations implements GuestAdyenDonationsInterface
{
    private AdyenDonations $adyenDonationsModel;
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private OrderRepository $orderRepository;

    public function __construct(
        AdyenDonations $adyenDonationsModel,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        OrderRepository $orderRepository
    ) {
        $this->adyenDonationsModel = $adyenDonationsModel;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param string $cartId
     * @param string $payload
     * @return void
     * @throws AdyenException
     * @throws LocalizedException
     */
    public function donate(string $cartId, string $payload): void
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        $order = $this->orderRepository->getOrderByQuoteId($quoteId);

        if (!$order) {
            throw new AdyenException('Donation Failed!');
        }

        $this->adyenDonationsModel->makeDonation($payload, $order);
    }
}

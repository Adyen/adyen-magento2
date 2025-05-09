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
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GuestAdyenDonations implements GuestAdyenDonationsInterface
{
    /**
     * @param AdyenDonations $adyenDonationsModel
     * @param OrderRepository $orderRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly AdyenDonations $adyenDonationsModel,
        private readonly OrderRepository $orderRepository,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param string $cartId
     * @param string $payload
     * @return void
     * @throws AdyenException
     * @throws LocalizedException
     */
    public function donate(string $cartId, string $payload): void
    {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        } catch (NoSuchEntityException $e) {
            $errorMessage = sprintf("Quote with masked ID %s not found!", $cartId);
            $this->adyenLogger->error($errorMessage);

            throw new AdyenException(__('Donation Failed!'));
        }

        $order = $this->orderRepository->getOrderByQuoteId($quoteId);

        if (!$order) {
            throw new AdyenException(__('Donation Failed!'));
        }

        $this->adyenDonationsModel->makeDonation($payload, $order);
    }
}

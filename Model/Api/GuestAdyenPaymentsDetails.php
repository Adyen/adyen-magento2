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

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\GuestAdyenPaymentsDetailsInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class GuestAdyenPaymentsDetails implements GuestAdyenPaymentsDetailsInterface
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param AdyenPaymentsDetails $adyenPaymentsDetails
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AdyenPaymentsDetails $adyenPaymentsDetails,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param string $payload
     * @param string $orderId
     * @param string $cartId
     * @return string
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @api
     */
    public function initiate(string $payload, string $orderId, string $cartId): string
    {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        } catch (NoSuchEntityException $e) {
            $errorMessage = sprintf("Quote with masked ID %s not found!", $cartId);
            $this->adyenLogger->error($errorMessage);

            throw new NotFoundException(
                __("The entity that was requested doesn't exist. Verify the entity and try again.")
            );
        }

        try {
            $order = $this->orderRepository->get(intval($orderId));
        } catch (NoSuchEntityException $e) {
            $errorMessage = sprintf("Order with ID %s not found!", $orderId);
            $this->adyenLogger->error($errorMessage);

            throw new NotFoundException(
                __("The entity that was requested doesn't exist. Verify the entity and try again.")
            );
        }

        if ($order->getQuoteId() != $quoteId) {
            throw new NotFoundException(
                __("The entity that was requested doesn't exist. Verify the entity and try again.")
            );
        }

        return $this->adyenPaymentsDetails->initiate($payload, $orderId);
    }
}

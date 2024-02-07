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
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class GuestAdyenPaymentsDetails implements GuestAdyenPaymentsDetailsInterface
{
    private OrderRepositoryInterface $orderRepository;
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private AdyenPaymentsDetails $adyenPaymentsDetails;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        AdyenPaymentsDetails $adyenPaymentsDetails
    ) {
        $this->orderRepository = $orderRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->adyenPaymentsDetails = $adyenPaymentsDetails;
    }

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
        $order = $this->orderRepository->get(intval($orderId));

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        if ($order->getQuoteId() != $quoteId) {
            throw new NotFoundException(
                __("The entity that was requested doesn't exist. Verify the entity and try again.")
            );
        }

        return $this->adyenPaymentsDetails->initiate($payload, $orderId);
    }
}

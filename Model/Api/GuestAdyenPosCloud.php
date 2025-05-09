<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\GuestAdyenPosCloudInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GuestAdyenPosCloud extends AdyenPosCloud implements GuestAdyenPosCloudInterface
{
    /**
     * @param CommandPoolInterface $commandPool
     * @param OrderRepository $orderRepository
     * @param PaymentDataObjectFactoryInterface $paymentDataObjectFactory
     * @param AdyenLogger $adyenLogger
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     */
    public function __construct(
        CommandPoolInterface $commandPool,
        OrderRepository $orderRepository,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        AdyenLogger $adyenLogger,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
    ) {
        parent::__construct(
            $commandPool,
            $orderRepository,
            $paymentDataObjectFactory,
            $adyenLogger
        );
    }

    /**
     * @param string $cartId
     * @return void
     * @throws NotFoundException
     */
    public function payByCart(string $cartId): void
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

        $order = $this->orderRepository->getOrderByQuoteId($quoteId);
        $this->execute($order);
    }
}

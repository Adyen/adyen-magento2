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
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;


class GuestAdyenPosCloud extends AdyenPosCloud implements GuestAdyenPosCloudInterface
{
    protected AdyenLogger $adyenLogger;
    protected OrderRepository $orderRepository;
    protected PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    public function __construct(
        CommandPoolInterface              $commandPool,
        OrderRepository                   $orderRepository,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        AdyenLogger                       $adyenLogger,
        QuoteIdMaskFactory                $quoteIdMaskFactory
    )
    {
        parent::__construct(
            $commandPool,
            $orderRepository,
            $paymentDataObjectFactory,
            $adyenLogger
        );
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * @param string $cartId
     * @return void
     */
    public function payByCart(string $cartId): void
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();
        $order = $this->orderRepository->getOrderByQuoteId($quoteId);
        $this->execute($order);
    }
}

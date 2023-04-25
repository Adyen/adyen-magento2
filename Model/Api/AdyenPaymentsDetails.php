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

use Adyen\Payment\Api\AdyenPaymentsDetailsInterface;
use Adyen\Payment\Helper\PaymentsDetails;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;

class AdyenPaymentsDetails implements AdyenPaymentsDetailsInterface
{
    private OrderRepositoryInterface $orderRepository;

    private PaymentsDetails $paymentsDetails;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        PaymentsDetails $paymentsDetails
    ) {
        $this->orderRepository = $orderRepository;
        $this->paymentsDetails = $paymentsDetails;
    }

    /**
     * @param string $payload
     * @param string $orderId
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @api
     */
    public function initiate(string $payload, string $orderId): string
    {
        $order = $this->orderRepository->get(intval($orderId));

        return $this->paymentsDetails->initiatePaymentDetails($order, $payload);
    }
}

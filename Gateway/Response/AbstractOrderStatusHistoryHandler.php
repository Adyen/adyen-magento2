<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\AdyenException;
use Adyen\Payment\Helper\OrderStatusHistory;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class AbstractOrderStatusHistoryHandler implements HandlerInterface
{
    /**
     * @param string $eventType Indicates the API call event type (authorization, capture etc.)
     * @param OrderStatusHistory $orderStatusHistoryHelper
     * @throws AdyenException
     */
    public function __construct(
        protected readonly string $eventType,
        protected readonly OrderStatusHistory $orderStatusHistoryHelper
    ) {
        if (empty($eventType)) {
            throw new AdyenException(
                __('Order status history can not be handled due to missing constructor argument!')
            );
        }
    }

    /**
     * @throws AdyenException
     */
    public function handle(array $handlingSubject, array $responseCollection): void
    {
        $readPayment = SubjectReader::readPayment($handlingSubject);
        $payment = $readPayment->getPayment();
        $order = $payment->getOrder();

        foreach ($responseCollection as $response) {
            $comment = $this->orderStatusHistoryHelper->buildApiResponseComment($response, $this->getEventType());
            $order->addCommentToStatusHistory($comment, $order->getStatus());
        }
    }

    /**
     * @return string
     */
    private function getEventType(): string
    {
        return $this->eventType;
    }
}

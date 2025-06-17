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
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class AbstractOrderStatusHistoryHandler implements HandlerInterface
{
    /**
     * @param string $actionDescription Indicates the API call event type (authorization, capture etc.)
     * @param string $apiEndpoint
     * @param OrderStatusHistory $orderStatusHistoryHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly string $actionDescription,
        private readonly string $apiEndpoint,
        private readonly OrderStatusHistory $orderStatusHistoryHelper,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @throws AdyenException
     */
    public function handle(array $handlingSubject, array $responseCollection): void
    {
        if (empty($this->actionDescription) || empty($this->apiEndpoint)) {
            $this->adyenLogger->error(
                __('Order status history can not be handled due to missing constructor arguments!')
            );
        } else {
            $readPayment = SubjectReader::readPayment($handlingSubject);
            $payment = $readPayment->getPayment();
            $order = $payment->getOrder();

            foreach ($responseCollection as $response) {
                $comment = $this->orderStatusHistoryHelper->buildApiResponseComment(
                    $response,
                    $this->actionDescription,
                    $this->apiEndpoint
                );
                $order->addCommentToStatusHistory($comment, $order->getStatus());
            }
        }
    }
}

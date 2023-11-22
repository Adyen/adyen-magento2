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

namespace Adyen\Payment\Observer;

use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Model\Method\Adapter;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\StatusResolver;

class SetOrderStateAfterPaymentObserver implements ObserverInterface
{
    private StatusResolver $statusResolver;

    public function __construct(
        StatusResolver $statusResolver
    ) {
        $this->statusResolver = $statusResolver;
    }

    /**
     * @throws LocalizedException
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        /** @var Payment $payment */
        $payment = $observer->getData('payment');
        $methodInstance = $payment->getMethodInstance();

        if ($methodInstance instanceof Adapter) {
            $order = $payment->getOrder();
            $resultCode = $payment->getAdditionalInformation(PaymentResponseInterface::RESULT_CODE);
            $action = $payment->getAdditionalInformation('action');
            $actionType = $action['type'] ?? 'Additional';

            /*
             * Set order status and state to pending_payment if an addition action is required.
             * This status will be changed when the shopper completes the action or returns from a redirection.
             */
            if (in_array($resultCode, PaymentResponseHandler::ACTION_REQUIRED_STATUSES)) {
                $status = $this->statusResolver->getOrderStatusByState(
                    $payment->getOrder(),
                    Order::STATE_PENDING_PAYMENT
                );
                $order->setState(Order::STATE_PENDING_PAYMENT);
                $order->setStatus($status);

                $message = sprintf(
                    __("%s action is required to complete the payment.<br>Result code: %"),
                    $actionType,
                    $resultCode
                );

                $order->addCommentToStatusHistory($message, $status);
                $order->save();
            }
        }
    }
}

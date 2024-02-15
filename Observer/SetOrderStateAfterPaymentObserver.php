<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

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

    public function __construct(StatusResolver $statusResolver)
    {
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
        $paymentMethod = $payment->getMethod();

        if ($paymentMethod === 'adyen_pos_cloud') {
            $this->handlePosPayment($payment);
        }
    }

    private function handlePosPayment(Payment $payment)
    {
        $order = $payment->getOrder();
        $status = $this->statusResolver->getOrderStatusByState(
            $payment->getOrder(),
            Order::STATE_PENDING_PAYMENT
        );
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus($status);
        $message = __("Pos payment initiated and waiting for payment");
        $order->addCommentToStatusHistory($message, $status);
        $order->save();
    }
}

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

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class SubmitQuoteObserver implements ObserverInterface
{
    private PaymentMethods $paymentMethodsHelper;

    public function __construct(
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    public function execute(Observer $observer)
    {
        /** @var  Order $order */
        $order = $observer->getEvent()->getOrder();
        /** @var Order\Payment $payment */
        $payment = $order->getPayment();
        $isAdyenPaymentMethod = $this->paymentMethodsHelper->isAdyenPayment($payment->getMethod());
        $isActionRequired = in_array(
            $payment->getAdditionalInformation('resultCode'),
            PaymentResponseHandler::ACTION_REQUIRED_STATUSES
        );
        $isPosPayment = $payment->getMethod() === 'adyen_pos_cloud';

        if ($isPosPayment || ($isAdyenPaymentMethod && $isActionRequired)) {
            // Further shopper action required (e.g. redirect or 3DS authentication)
            /** @var Quote $quote */
            $quote = $observer->getEvent()->getQuote();
            // Keep cart active until such actions are taken
            $quote->setIsActive(true);
        }
    }
}

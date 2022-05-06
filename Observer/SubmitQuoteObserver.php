<?php

namespace Adyen\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class SubmitQuoteObserver implements ObserverInterface
{
    const PAYMENT_COMPLETE = ['Authorised', 'Received', 'PresentToShopper'];

    public function execute(Observer $observer)
    {
        /** @var  Order $order */
        $order = $observer->getEvent()->getOrder();
        /** @var Order\Payment $payment */
        $payment = $order->getPayment();

        // No further shopper action required
        $resultCode = $payment->getAdditionalInformation('resultCode');
        if (in_array($resultCode, self::PAYMENT_COMPLETE, true)) {
            return;
        }

        // Further shopper action required (e.g. redirect or 3DS authentication)
        if (in_array($payment->getMethod(), ['adyen_hpp', 'adyen_cc', 'adyen_oneclick'], true)) {
            /** @var Quote $quote */
            $quote = $observer->getEvent()->getQuote();
            // Keep cart active until such actions are taken
            $quote->setIsActive(true);
        }
    }
}

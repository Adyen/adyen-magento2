<?php

namespace Adyen\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;

/**
 * Keep cart active based on payment method
 */
class SubmitQuoteObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        $method = $quote->getPayment()->getMethod();
        if (in_array($method, ['adyen_hpp', 'adyen_cc'], true)) {
            $quote->setIsActive(true);
        }
    }
}

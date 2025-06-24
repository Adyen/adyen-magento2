<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;

/**
 * Class ResetQuoteReservedOrderId
 *
 * @package Adyen\Payment\Observer
 */
class ResetQuoteReservedOrderId implements ObserverInterface
{
    /**
     * ResetReservedOrderId Constructor
     *
     * @param PaymentMethods $paymentMethodsHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        protected PaymentMethods $paymentMethodsHelper,
        protected AdyenLogger $adyenLogger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getData('quote');
        if (!$quote instanceof Quote) {
            return;
        }

        try {
            $method = $quote->getPayment()->getMethod();
            if ($this->paymentMethodsHelper->isAdyenPayment($method)) {
                $quote->setReservedOrderId(null);
            }
        } catch (\Exception $e) {
            $this->adyenLogger->error("Failed to reset reservedOrderId for guest shopper" . $e->getMessage(), [
                'quote_id'
            ]);
        }
    }
}
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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class SubmitQuoteObserver implements ObserverInterface
{
    /**
     * @var PaymentMethods
     */
    private PaymentMethods $paymentMethodsHelper;

    /**
     * @var Config
     */
    private Config $configHelper;

    /**
     * @param PaymentMethods $paymentMethodsHelper
     * @param Config $configHelper
     */
    public function __construct(
        PaymentMethods $paymentMethodsHelper,
        Config $configHelper
    ) {
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->configHelper = $configHelper;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var  Order $order */
        $order = $observer->getEvent()->getOrder();

        /** @var Order\Payment $payment */
        $payment = $order->getPayment();
        $storeId = $payment->getOrder()->getStoreId();

        $isAdyenPaymentMethod = $this->paymentMethodsHelper->isAdyenPayment($payment->getMethod());
        $isActionRequired = in_array(
            $payment->getAdditionalInformation('resultCode'),
            PaymentResponseHandler::ACTION_REQUIRED_STATUSES
        );

        $isPosPayment = $payment->getMethod() === 'adyen_pos_cloud';
        $posPaymentAction = $this->configHelper->getAdyenPosCloudPaymentAction($storeId);

        if (($isPosPayment && $posPaymentAction === MethodInterface::ACTION_ORDER) ||
            ($isAdyenPaymentMethod && $isActionRequired)) {
            // Further shopper action required (e.g. redirect or 3DS authentication)
            /** @var Quote $quote */
            $quote = $observer->getEvent()->getQuote();
            // Keep cart active until such actions are taken
            $quote->setIsActive(true);
        }
    }
}

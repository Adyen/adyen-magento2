<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RecurringDataBuilder implements BuilderInterface
{
    /** @var Requests  */
    private $adyenRequestsHelper;

    /** @var AdyenLogger  */
    private $adyenLogger;

    /** @var Order */
    private $orderHelper;

    public function __construct(
        Requests $adyenRequestsHelper,
        AdyenLogger $adyenLogger,
        Order $orderHelper
    ) {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
        $this->adyenLogger = $adyenLogger;
        $this->orderHelper = $orderHelper;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $body = [];
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();
        $method = $payment->getMethod();

        if ($method === PaymentMethods::ADYEN_CC) {
            $body = $this->adyenRequestsHelper->buildCardRecurringData($storeId, $payment);
        } elseif ($method === PaymentMethods::ADYEN_HPP) {
            $body = $this->adyenRequestsHelper->buildAlternativePaymentRecurringData($storeId, $payment);
        } elseif ($method === PaymentMethods::ADYEN_ONE_CLICK) {
            $body = $this->adyenRequestsHelper->buildAdyenTokenizedPaymentRecurringData($storeId, $payment);
        } else {
            $this->adyenLogger->addAdyenWarning(
                sprintf('Unknown payment method: %s', $payment->getMethod()),
                $this->orderHelper->getLogOrderContext($order)
            );
        }

        return [
            'body' => $body
        ];
    }
}

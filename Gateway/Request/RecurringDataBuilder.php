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

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RecurringDataBuilder implements BuilderInterface
{
    /** @var Requests */
    private $adyenRequestsHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var Vault */
    private $vaultHelper;

    /** @var StateData */
    private $stateData;

    public function __construct(
        Requests    $adyenRequestsHelper,
        AdyenLogger $adyenLogger,
        Vault       $vaultHelper,
        StateData   $stateData
    )
    {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
        $this->adyenLogger = $adyenLogger;
        $this->vaultHelper = $vaultHelper;
        $this->stateData = $stateData;
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
            $brand = $this->stateData->getPaymentMethodVariant($order->getQuoteId());
            $body = $this->vaultHelper->buildPaymentMethodRecurringData($storeId, $brand);
        } elseif ($method === PaymentMethods::ADYEN_ONE_CLICK) {
            $body = $this->adyenRequestsHelper->buildAdyenTokenizedPaymentRecurringData($storeId, $payment);
        } elseif ($method === PaymentMethods::ADYEN_PAY_BY_LINK) {
            return [
                'body' => $body
            ];
        } else {
            $this->adyenLogger->addAdyenWarning(
                sprintf('Unknown payment method: %s', $payment->getMethod()),
                $this->adyenLogger->getOrderContext($order)
            );
        }

        return [
            'body' => $body
        ];
    }
}

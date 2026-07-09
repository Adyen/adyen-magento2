<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\Config;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Adyen\Payment\Enum\AdyenRefusalReason;
use Adyen\Payment\Model\TestingRefusalReason;

/**
 * Class TestingRefusalReasonBuilder
 *
 * @package Adyen\Payment\Gateway\Request
 * https://docs.adyen.com/development-resources/testing/result-codes/?tab=request_using_holder_name_0_1
 */
class TestingRefusalReasonBuilder implements BuilderInterface
{
    /**
     * TestingRefusalReasonBuilder Constructor
     *
     * @param Config $config
     * @param TestingRefusalReason $testingRefusalReason
     */
    public function __construct(
        protected Config $config,
        protected TestingRefusalReason $testingRefusalReason
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        /** @var Order $order */
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        if (!$this->config->isDemoMode($storeId)) {
            return [];
        }

        $reason = $this->testingRefusalReason->findRefusalReason($order);
        if (!$reason instanceof AdyenRefusalReason) {
            return [];
        }

        return [
            'body' => [
                'additionalData' => [
                    'RequestedTestAcquirerResponseCode' => $reason->value,
                ],
            ],
        ];
    }
}

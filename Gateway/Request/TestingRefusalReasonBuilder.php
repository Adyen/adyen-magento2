<?php
/**
 * Copyright © Reflet Digital, all rights reserved.
 * See LICENSE_REFLET.txt for license details.
 */
declare(strict_types=1);

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

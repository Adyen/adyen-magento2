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

namespace Adyen\Payment\Model;

use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use Adyen\Payment\Enum\AdyenRefusalReason;
use Adyen\Payment\Enum\CallbackOrderProperty;
use Adyen\Payment\Helper\Config\Testing;

/**
 * Class TestingRefusalReason
 *
 * @package Adyen\Payment\Model
 */
class TestingRefusalReason
{
    /**
     * TestingRefusalReason Constructor
     *
     * @param Testing $testingConfig
     */
    public function __construct(
        protected Testing $testingConfig,
    ) {
    }

    /**
     * @param Order $order
     * @return AdyenRefusalReason|null
     */
    public function findRefusalReason(Order $order): ?AdyenRefusalReason
    {
        $storeId = (int)$order->getStoreId();
        $source = $this->testingConfig->getRefusalReasonValueSource($storeId);
        $value = $this->getSourceValue($order, $source);
        if ($value === null || $value === '') {
            return null;
        }

        $mapping = $this->testingConfig->getRefusalReasonMapping($storeId);
        return $mapping[$value] ?? null;
    }

    /**
     * @param Order $order
     * @param CallbackOrderProperty $source
     * @return mixed
     */
    protected function getSourceValue(Order $order, CallbackOrderProperty $source): mixed
    {
        $parts = explode('.', $source->value);
        if (count($parts) === 1) {
            return $order->getData($source->value);
        }

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid source value');
        }

        $root = $parts[0];
        $key = $parts[1];

        $value = match ($root) {
            'shipping' => $order->getShippingAddress(),
            'billing' => $order->getBillingAddress(),
        };

        if (!$value instanceof DataObject) {
            return null;
        }

        return $value->getData($key);
    }
}

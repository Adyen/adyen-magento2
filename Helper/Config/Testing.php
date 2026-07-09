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

namespace Adyen\Payment\Helper\Config;

use Adyen\Payment\Helper\Config;
use Magento\Framework\Serialize\Serializer\Json;
use Adyen\Payment\Enum\AdyenRefusalReason;
use Adyen\Payment\Enum\CallbackOrderProperty;

/**
 * Class Testing
 *
 * @package Adyen\Payment\Helper\Config
 */
class Testing
{
    const XML_REFUSAL_REASON_VALUE_SOURCE = 'testing_refusal_reason_value_source';
    const XML_REFUSAL_REASON_MAPPING = 'testing_refusal_reason_mapping';

    /**
     * Testing Constructor
     *
     * @param Config $config
     * @param Json $serializer
     */
    public function __construct(
        protected Config $config,
        protected Json $serializer,
    ) {
    }

    /**
     * @param int|null $storeId
     * @return CallbackOrderProperty
     */
    public function getRefusalReasonValueSource(?int $storeId = null): CallbackOrderProperty
    {
        $value = $this->config->getConfigData(
            self::XML_REFUSAL_REASON_VALUE_SOURCE,
            $this->config::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );

        $callback = null;
        if (is_string($value) && !empty($value)) {
            $callback = CallbackOrderProperty::tryFrom($value);
        }

        if (!$callback instanceof CallbackOrderProperty) {
            $callback = CallbackOrderProperty::ShippingLastName;
        }

        return $callback;
    }

    /**
     * @param int|null $storeId
     * @return array<string, AdyenRefusalReason>
     */
    public function getRefusalReasonMapping(?int $storeId = null): array
    {
        $value = $this->config->getConfigData(
            self::XML_REFUSAL_REASON_MAPPING,
            $this->config::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );

        if (!is_string($value) || $value === '') {
            return [];
        }

        $rows = $this->serializer->unserialize($value);
        $mapping = [];

        foreach ($rows as $row) {
            $value = $row['value'] ?? null;
            $reason = $row['refusal_reason'] ?? null;

            if (!$value || !is_numeric($reason)) {
                continue;
            }

            $reason = AdyenRefusalReason::tryFrom((int)$reason);
            if (!$reason instanceof AdyenRefusalReason) {
                continue;
            }

            $mapping[$value] = $reason;
        }

        return $mapping;
    }
}

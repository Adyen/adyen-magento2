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

namespace Adyen\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Adyen\Payment\Enum\CallbackOrderProperty;

/**
 * Class OrderCallbackProperty
 *
 * @package Adyen\Payment\Model\Config\Source
 */
class OrderCallbackProperty implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return array_map(fn (CallbackOrderProperty $property) => [
            'value' => $property->value,
            'label' => $property->getLabel(),
        ], CallbackOrderProperty::cases());
    }
}

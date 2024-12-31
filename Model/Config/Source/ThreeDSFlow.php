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

namespace Adyen\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ThreeDSFlow implements OptionSourceInterface
{
    const THREEDS_NATIVE = 'native';
    const THREEDS_REDIRECT = 'redirect';

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::THREEDS_NATIVE, 'label' => __('Native 3D Secure 2')],
            ['value' => self::THREEDS_REDIRECT, 'label' => __('Redirect 3D Secure 2')],
        ];
    }
}

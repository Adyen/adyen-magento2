<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

use Adyen\Region;
use Magento\Framework\Data\OptionSourceInterface;

class TerminalApiRegion implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Region::EU, 'label' => __('Default (EU - Europe)')],
            ['value' => Region::US, 'label' => __('US - United States')],
            ['value' => Region::AU, 'label' => __('AU - Australia')],
            ['value' => Region::APSE, 'label' => __('APSE - Asia Pacific Southeast')]
        ];
    }
}

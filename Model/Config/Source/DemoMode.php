<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

class DemoMode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $modes = $this->getModes();

        foreach ($modes as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options;
    }

    /**
     * return modes for configuration setting
     *
     * @return array
     */
    private function getModes()
    {
        return [
            '1' => 'Test',
            '0' => 'Live'
        ];
    }
}

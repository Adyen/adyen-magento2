<?php

namespace Adyen\Payment\Model\Config\Source;

class ConfigurationMode implements \Magento\Framework\Option\ArrayInterface
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

    private function getModes()
    {
        return [
            'manual' => 'Manual',
            'auto' => 'Automated'
        ];
    }
}
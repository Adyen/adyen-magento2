<?php


namespace Adyen\Payment\Model\Config\Source;

class MerchantAccounts implements \Magento\Framework\Data\OptionSourceInterface
{

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 0,
                'label' => __('Page1'),
            ],
            [
                'value' => 1,
                'label' => __('Page2'),
            ],
            [
                'value' => 2,
                'label' => __('Page3'),
            ],
        ];
    }
}

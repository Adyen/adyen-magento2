<?php

namespace Adyen\Payment\Model\Config\Source;

use Magento\Config\Model\Config\Source\Locale as MagentoLocale;

class InheritableLocale extends MagentoLocale
{
    public function toOptionArray()
    {
        $locales = parent::toOptionArray();
        array_unshift($locales, [
            'value' => '',
            'label' => 'Use system locale'
        ]);
        return $locales;
    }
}
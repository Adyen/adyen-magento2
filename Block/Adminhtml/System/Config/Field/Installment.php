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

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

use Magento\Framework\View\Element\Html\Select;

class Installment extends Select
{
    private function getSourceOptions(): array
    {
        return [
            ['label' => '1x', 'value' => '1'],
            ['label' => '2x', 'value' => '2'],
            ['label' => '3x', 'value' => '3'],
            ['label' => '4x', 'value' => '4'],
            ['label' => '5x', 'value' => '5'],
            ['label' => '6x', 'value' => '6'],
            ['label' => '7x', 'value' => '7'],
            ['label' => '8x', 'value' => '8'],
            ['label' => '9x', 'value' => '9'],
            ['label' => '10x', 'value' => '10'],
            ['label' => '11x', 'value' => '11'],
            ['label' => '12x', 'value' => '12'],
            ['label' => '13x', 'value' => '13'],
            ['label' => '14x', 'value' => '14'],
            ['label' => '15x', 'value' => '15'],
            ['label' => '16x', 'value' => '16'],
            ['label' => '17x', 'value' => '17'],
            ['label' => '18x', 'value' => '18'],
            ['label' => '19x', 'value' => '19'],
            ['label' => '20x', 'value' => '20'],
            ['label' => '21x', 'value' => '21'],
            ['label' => '22x', 'value' => '22'],
            ['label' => '23x', 'value' => '23'],
            ['label' => '24x', 'value' => '24']
        ];
    }

    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    public function setInputName(string $value): Installment
    {
        return $this->setName($value);
    }

    public function setInputId(string $value): Installment
    {
        return $this->setId($value);
    }
}

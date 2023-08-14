<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

use Magento\Framework\View\Element\Html\Select;

class MotoEnviroment extends Select
{
    private function getSourceOptions(): array
    {
        return [
            ['label' => 'Live', 'value' => '0'],
            ['label' => 'Test', 'value' => '1'],
        ];
    }

    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    public function setInputName(string $value): MotoEnviroment
    {
        return $this->setName($value);
    }

    public function setInputId(string $value): MotoEnviroment
    {
        return $this->setId($value);
    }
}

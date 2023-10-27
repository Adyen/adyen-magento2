<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

use Magento\Framework\View\Element\Html\Select;

class SelectYesNo extends Select
{
    private function getSourceOptions(): array
    {
        return [
            ['label' => 'No', 'value' => '0'],
            ['label' => 'Yes', 'value' => '1'],
        ];
    }

    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    public function setInputName(string $value): SelectYesNo
    {
        return $this->setName($value);
    }

    public function setInputId(string $value): SelectYesNo
    {
        return $this->setId($value);
    }
}

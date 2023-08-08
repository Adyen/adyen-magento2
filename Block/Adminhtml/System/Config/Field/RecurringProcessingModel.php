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

use Adyen\Payment\Helper\Vault;
use Magento\Framework\View\Element\Html\Select;

class RecurringProcessingModel extends Select
{
    private function getSourceOptions(): array
    {
        return [
            ['label' => 'Card on File', 'value' => Vault::CARD_ON_FILE],
            ['label' => 'Unscheduled Card on File', 'value' => Vault::UNSCHEDULED_CARD_ON_FILE],
            ['label' => 'Subscription', 'value' => Vault::SUBSCRIPTION]
        ];
    }

    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    public function setInputName(string $value): RecurringProcessingModel
    {
        return $this->setName($value);
    }

    public function setInputId(string $value): RecurringProcessingModel
    {
        return $this->setId($value);
    }
}

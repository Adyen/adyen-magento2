<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

use Magento\Framework\View\Element\Html\Select;
use Adyen\Payment\Enum\AdyenRefusalReason;

/**
 * Class RefusalReasonSelect
 *
 * @package Adyen\Payment\Block\Adminhtml\System\Config\Field
 */
class RefusalReasonSelect extends Select
{
    /**
     * @param string $value
     * @return self
     */
    public function setInputName(string $value): self
    {
        return $this->setData('name', $value);
    }

    /**
     * @param $value
     * @return self
     */
    public function setInputId($value): self
    {
        return $this->setId($value);
    }

    /**
     * @inheritDoc
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $options = array_map(fn (AdyenRefusalReason $reason) => [
                'label' => $reason->getLabel(),
                'value' => $reason->value,
            ], AdyenRefusalReason::cases());

            $this->setOptions($options);
        }

        return parent::_toHtml();
    }
}

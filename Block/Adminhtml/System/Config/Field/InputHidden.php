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

use Magento\Framework\View\Element\Template;

class InputHidden extends Template
{
    public function _toHtml(): string
    {
        $inputName = $this->getInputName();
        $column = $this->getColumn();

        return '<input type="hidden" readonly id="' . $this->getInputId().'" name="' . $inputName . '" ' .
            ($column['size'] ? 'size="' . $column['size'] . '"' : '') . ' class="' . '"'.
            (isset($column['style']) ? ' style="'.$column['style'] . '"' : '') . '/>';
    }
}

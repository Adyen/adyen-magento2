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

class ApiKey extends \Magento\Framework\View\Element\Template
{
    /**
     * @return string
     */
    public function _toHtml()
    {
        $inputName = $this->getInputName();
        $column = $this->getColumn();

        return '<input type="password" id="' . $this->getInputId().'" name="' . $inputName . '" ' .
            ($column['size'] ? 'size="' . $column['size'] . '"' : '') . ' class="' . '"'.
            (isset($column['style']) ? ' style="'.$column['style'] . '"' : '') . '/>';
    }
}
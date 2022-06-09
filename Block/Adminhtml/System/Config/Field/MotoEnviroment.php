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

class MotoEnviroment extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * Options
     *
     * @var array
     */
    protected $_options = [
        '0' => 'Live',
        '1' => 'Test'
    ];

    /**
     * Sets name for input element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }
}

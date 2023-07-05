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
    /**
     * Options
     *
     * @var array
     */
    protected $options = [
        Vault::CARD_ON_FILE => 'Card on File',
        Vault::UNSCHEDULED_CARD_ON_FILE => 'Unscheduled Card on File',
        Vault::SUBSCRIPTION => 'Subscription'
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

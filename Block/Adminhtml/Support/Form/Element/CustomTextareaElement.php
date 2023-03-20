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

namespace Adyen\Payment\Block\Adminhtml\Support\Form\Element;

use Magento\Framework\Data\Form\Element\Textarea;

class CustomTextareaElement extends Textarea
{
    public function getHtmlAttributes()
    {
        return array_merge(parent::getHtmlAttributes(), ['placeholder']);
    }
}

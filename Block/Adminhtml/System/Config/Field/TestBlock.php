<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

/**
 * Field renderer for Adyen module version
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

class TestBlock extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * Retrieve the setup version of the extension
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return "
           <input type='hidden' name='groups[adyen_group_all_in_one][groups][adyen_configure_payment_methods][groups][adyen_cc][fields][cbtest][value]' value='0'>
           <input type='checkbox' class='required-entry' name='groups[adyen_group_all_in_one][groups][adyen_configure_payment_methods][groups][adyen_cc][fields][cbtest][value]' value='1' checked>
           ";

    }
}
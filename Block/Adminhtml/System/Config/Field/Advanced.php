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

class Advanced extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * [Template path]
     *
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/toggle.phtml';

    /**
     * Render fieldset html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->_decorateRowHtml($element, "<td class='label'>".$element->getLabelHtml() .'</td><td>'. $this->toHtml() . '</td>');
    }
}
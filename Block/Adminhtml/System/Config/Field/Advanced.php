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
     * [Template path]
     *
     * @var string
     */
    protected $_groupName = 'toggleme';

    /**
     * [Template path]
     *
     * @var string
     */
    protected $_fieldName = 'status';

    /**
     * [Template path]
     *
     * @var string
     */
    protected $_configPath = 'Adyen_Payment::';

    /**
     * Render fieldset html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element): string
    {
        return $this->_decorateRowHtml($element, "<td class='label'>" . $element->getLabel() .'</td><td>'. $this->toHtml() . '</td>');
    }

    public function getGroupName() {
        return $this->_groupName;
    }

    public function getFieldName() {
        return $this->_fieldName;
    }

    public function getConfigPath() {
        return $this->_configPath;
    }
}
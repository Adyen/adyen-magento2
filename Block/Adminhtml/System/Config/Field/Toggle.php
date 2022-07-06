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

class Toggle extends \Magento\Config\Block\System\Config\Form\Field
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
    protected $groupName;

    /**
     * [Template path]
     *
     * @var string
     */
    protected $myStatus;


    /**
     * Render fieldset html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */

    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element): string
    {
        $this->groupName = $element->getName();
        $this->myStatus = $element->getData()['value'];



        return $this->_decorateRowHtml($element, "<td class='label'>" . $element->getLabel() .'</td><td>'. $this->toHtml() . '</td>');
    }

    public function getGroupName() {
        return $this->groupName;
    }

    public function getMyStatus() {
        return $this->myStatus;
    }
}

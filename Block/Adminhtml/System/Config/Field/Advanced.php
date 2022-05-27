<?php


namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;


class Advanced extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * Template path
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
        return $this->_decorateRowHtml($element, "<td class='label'>Label Text</td><td>" . $this->toHtml() . '</td><td></td>');
    }
}
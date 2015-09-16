<?php

/**
 * Field renderer for PayPal merchant country selector
 */
namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;


class Version extends \Magento\Config\Block\System\Config\Form\Field
{
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        // TODO: make dynamic
        //$configVer = $this->moduleList->getOne($moduleName)['setup_version'];
        return (string) "0.1.0";
    }
}
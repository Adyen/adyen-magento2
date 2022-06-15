<?php

namespace Adyen\Payment\Model\Config\Adminhtml;

use Magento\Config\Block\System\Config\Form\Field;

class ConfigurationMode extends Field
{
    protected $_template = 'Adyen_Payment::config/configuration_wizard.phtml';

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return parent::_getElementHtml($element) . $this->_toHtml();
    }

    public function getMerchantAccountsUrl()
    {
        return $this->getUrl('adyen/configuration/merchantaccounts');
    }

    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    public function configured() {
        return false;
    }
}

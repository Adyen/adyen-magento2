<?php

namespace Adyen\Payment\Model\Config\Adminhtml;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ConfigurationWizard extends Field
{
    protected $_template = 'Adyen_Payment::config/configuration_wizard.phtml';

    public function render(AbstractElement $element)
    {
        // Remove scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'adyen_configuration_action',
                'label' => __('Next'),
            ]
        );

        return $button->toHtml();
    }

    public function getMerchantAccountsUrl()
    {
        return $this->getUrl('adyen/configuration/merchantaccounts');
    }

    public function getClientKeyUrl()
    {
        return $this->getUrl('adyen/configuration/me');
    }

    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    public function configured() {
        return false;
    }
}

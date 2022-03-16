<?php
namespace Adyen\Payment\Model\Config\Adminhtml;

class MerchantAccounts extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_template = 'Adyen_Payment::config/required_settings.phtml';

    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl()
    {
        return $this->getUrl('adyen/configuration/merchantaccounts');
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'adyen_configure_merchants',
                'label' => __('Get your merchant accounts from Adyen'),
            ]
        );

        return $button->toHtml();
    }

    public function getDisabledButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'adyen_configure_merchants',
                'label' => __('Configure'),
                'disabled' => true
            ]
        );

        return $button->toHtml();
    }
}

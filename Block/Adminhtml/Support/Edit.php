<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml\Support;

use Magento\Backend\Block\Widget\Form\Container;

class Edit extends Container
{
    /**
     * Internal constructor
     */
    protected function _construct()
    {
        $this->_blockGroup = 'Adyen_Payment';
        $this->_controller = 'adminhtml_support';
        parent::_construct();
    }

    /**
     * Return save url for edit form
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/*', ['_current' => true, 'back' => false]);
    }

    protected function _prepareLayout(): self
    {
        $this->removeButton('back');
        $this->removeButton('reset');
        $this->removeButton('save');

        $this->addButton(
            'send',
            [
                'label' => __('Send'),
                'class' => 'primary',
                'data_attribute' => [
                    'mage-init' => ['button' => ['event' => 'send', 'target' => '#support_form']],
                ]
            ],
            1
        );

        return parent::_prepareLayout();
    }
}

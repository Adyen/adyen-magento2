<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;

class Edit extends Container
{
    /**
     * Core registry
     *
     * @var Registry
     */
    protected $_coreRegistry = null;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_blockGroup = 'Adyen_Payment';
        $this->_controller = 'adminhtml_support_form';
        parent::_construct();
    }

    /**
     * Return validation url for edit form
     *
     * @return string
     */
    public function getValidationUrl()
    {
        return $this->getUrl('*/*/validate', ['_current' => true]);
    }

    /**
     * Return save url for edit form
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/*', ['_current' => true, 'back' => false]);
    }

    protected function _prepareLayout()
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
                    'mage-init' => ['button' => ['event' => 'send', 'target' => '#edit_form']],
                ]
            ],
            1
        );

        return parent::_prepareLayout();
    }
}

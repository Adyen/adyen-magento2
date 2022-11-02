<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml\Support\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Design\Theme\LabelFactory;
use Magento\Store\Model\System\Store;

class ConfigurationSettings extends Generic implements TabInterface
{
    const HEADLESS_YES = 1;
    const HEADLESS_NO = 0;

    /**
     * @var Store
     */
    protected $_store;

    /**
     * @var LabelFactory
     */
    protected $_themeLabelFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Store $store
     * @param LabelFactory $themeLabelFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Store $store,
        LabelFactory $themeLabelFactory,
        array $data = []
    ) {
        $this->_store = $store;
        $this->_themeLabelFactory = $themeLabelFactory;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setActive(true);
    }

    /**
     * Prepare label for tab
     */
    public function getTabLabel()
    {
        return __('Configuration settings');
    }

    /**
     * Prepare title for tab
     */
    public function getTabTitle()
    {
        return __('Configuration settings');
    }

    /**
     * Returns status flag about this tab can be shown or not
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Returns status flag about this tab hidden or not
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Prepare form before rendering HTML
     *
     * @return $this
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareForm()
    {
        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'support_form',
                'action' => $this->getData('action'),
                'method' => 'post'
            ]
        ]);

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Configuration settings')]);
        $this->_addElementTypes($fieldset);
        $fieldset->addField(
            'pspReference',
            'text',
            [
                'name' => 'pspReferenceConfigurationSettings',
                'label' => __('PSP Reference'),
                'title' => __('PSP Reference'),
                'class' => '',
                'required' => true
            ]
        );
        $fieldset->addField(
            'merchantReference',
            'text',
            [
                'name' => 'merchantReference',
                'label' => __('Merchant Reference'),
                'title' => __('Merchant Reference'),
                'class' => '',
                'required' => false,
            ]
        );
        $fieldset->addField(
            'headless',
            'radios',
            [
                'name' => 'headless',
                'label' => __('Are you using headless integration?'),
                'title' => __('Are you using headless integration?'),
                'class' => '',
                'required' => false,
                'values' => [
                    ['value' => self::HEADLESS_YES, 'label' => __('Yes')],
                    ['value' => self::HEADLESS_NO, 'label' => __('No')]
                ]
            ]
        );
        $fieldset->addField(
            'terminalId',
            'text',
            [
                'name' => 'terminalId',
                'label' => __('Terminal ID number'),
                'title' => __('Terminal ID number'),
                'class' => '',
                'required' => false,
            ]
        );
        $fieldset->addField(
            'logs',
            'file',
            [
                'name' => 'logs',
                'label' => __('Attach Logs'),
                'title' => __('Attach Logs'),
                'class' => '',
                'required' => false,
            ]
        );
        $fieldset->addField(
            'orderHistoryComments',
            'textarea',
            [
                'name' => 'orderHistoryComments',
                'label' => __('Order History Comments'),
                'title' => __('Order History Comments'),
                'class' => '',
                'required' => false,
            ]
        );

        $this->setForm($form);
        return parent::_prepareForm();
    }
}

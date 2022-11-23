<?php

namespace Adyen\Payment\Block\Adminhtml\Support;

use Adyen\Payment\Block\Adminhtml\Support\Edit\Tab\ConfigurationSettings;

class ConfigurationSettingsForm extends \Magento\Backend\Block\Widget\Form\Generic
{
    const HEADLESS_YES = 1;
    const HEADLESS_NO = 0;
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
                'id' => 'configurationsettings_form',
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
        $fieldset->addField(
            'upload_button',
            'button',
            [
                'name' => 'submit',

                'title' => __('click'),
                'class' => 'primary',
                'data_attribute' => '',
                'value' => 'Submit',
                'data_attribute' => [
                    'mage-init' => ['button' => ['event' => 'send', 'target' => '#support_form']],
                ]
            ]
        );


        $this->setForm($form);
        return parent::_prepareForm();
    }


    public function getSupportTopics(): array
    {
        return [
            'required_settings' => 'Required Settings',
            'card_payments' => 'Card payments',
            'card_tokenization' => 'Card tokenization',
            'alt_payment_methods' => 'Alternative payment methods',
            'pos_integration' => 'POS integration with cloud',
            'pay_by_link' => 'Pay By Link',
            'adyen_giving' => 'Adyen Giving',
            'advanced_settings' => 'Advanced settings'
        ];
    }

    public function getIssuesTopics(): array
    {
        return [
            'invalid_origin' => 'Invalid Origin',
            'headles_state_data_actions' => 'Headless state data actions',
            'refund' => 'Refund',
            'other' => 'Other'
        ];
    }
}

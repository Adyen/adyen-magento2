<?php

namespace Adyen\Payment\Block\Adminhtml\Support;

use Adyen\Payment\Block\Adminhtml\Support\Edit\Tab\ConfigurationSettings;

class ConfigurationSettingsForm extends \Magento\Backend\Block\Widget\Form\Generic
{
    const HEADLESS_YES = 1;
    const HEADLESS_NO = 0;

    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setActive(true);
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
                'id' => 'configurationsettings_form',
                'action' => $this->getUrl('adyen/support/configurationsettingsform'),
                'method' => 'post'
            ]
        ]);

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Configuration settings')]);
        $this->_addElementTypes($fieldset);

        $fieldset->addField(
            'topic',
            'select',
            [
                'name' => 'topic',
                'label' => __('Topic'),
                'title' => __('Topic'),
                'class' => '',
                'options' => $this->getSupportTopics(),
                'required' => true
            ]
        );

        $fieldset->addField(
            'issue',
            'select',
            [
                'name' => 'issue',
                'label' => __('Issue'),
                'title' => __('Issue'),
                'class' => '',
                'options' => $this->getIssuesTopics(),
                'required' => true
            ]
        );
        $fieldset->addField(
            'subject',
            'text',
            [
                'name' => 'subject',
                'label' => __('Subject'),
                'title' => __('Subject'),
                'class' => '',
                'required' => true
            ]
        );
        $fieldset->addField(
            'email',
            'text',
            [
                'name' => 'email',
                'label' => __('Email'),
                'title' => __('Email'),
                'class' => '',
                'required' => true,
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
            'description',
            'textarea',
            [
                'name' => 'descriptionComments',
                'label' => __('Description'),
                'title' => __('Description'),
                'class' => '',
                'required' => false,
            ]
        );

        $fieldset->addField(
            'submit_support_configuration_settings',
            'submit',
            [
                'name' => 'submit',
                'title' => __('Submit'),
                'class' => 'primary',
                'value' => 'Submit'
            ]
        );

        $form->setUseContainer(true);

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
            'headless_state_data_actions' => 'Headless state data actions',
            'refund' => 'Refund',
            'other' => 'Other'
        ];
    }
}

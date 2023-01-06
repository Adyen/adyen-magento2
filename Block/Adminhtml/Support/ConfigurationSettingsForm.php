<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\Support;

use Adyen\Payment\Helper\SupportFormHelper;

class ConfigurationSettingsForm extends \Magento\Backend\Block\Widget\Form\Generic
{
    const HEADLESS_YES = 1;
    const HEADLESS_NO = 0;

    private $supportFormHelper;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry             $registry,
        \Magento\Framework\Data\FormFactory     $formFactory,
        SupportFormHelper                       $supportFormHelper
    )
    {
        $this->supportFormHelper = $supportFormHelper;
        parent::__construct($context, $registry, $formFactory);
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
                'id' => 'support_form',
                'action' => $this->getUrl('adyen/support/configurationsettingsform'),
                'method' => 'post',
                'enctype' => 'multipart/form-data',
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
                'required' => true,
                'value' => $this->getRequest()->getParam('topic'),
            ]
        );
        $fieldset->addField(
            'issue',
            'select',
            [
                'name' => 'issue',
                'label' => __('Issue'),
                'title' => __('Issue'),
                'class' => 'tooltip',
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
                'class' => 'adyen_support-form',
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
                'class' => 'validate-emails required',
                'required' => true,
                'value' => $this->supportFormHelper->getGeneralContactSenderEmail(),
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
                'required' => false, 'values' => [
                ['value' => self::HEADLESS_YES, 'label' => __('Yes')],
                ['value' => self::HEADLESS_NO, 'label' => __('No')]]
            ])->setAfterElementHtml('
       <div class="tooltip">
       <span class="help">
       <span></span>
       </span>
       <div class="tooltip-content">
       Headless integration is when you use Adyen pre-configured backend but have a custom store frontend.
            </div>
       </div>');

        $fieldset->addType('file', 'Adyen\Payment\Block\Adminhtml\Support\Form\Element\MultipleFileElement');
        $fieldset->addField(
            'attachments',
            'file',
            [
                'name' => 'attachments[]',
                'multiple' => 'multiple',
                'label' => __('Attachments'),
                'title' => __('Attachments'),
                'class' => 'adyen_support-form',
                'required' => false
            ]
        )->setAfterElementHtml('
       <div class="tooltip">
       <span class="help">
       <span></span>
       </span>
       <div class="tooltip-content">Sending us a file often helps us to solve your issue. 
       We accept files in PNG, JPG, ZIP, RAR, or SVG format, with a maximum size of 10 MB.
            </div>
       </div>');

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

<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\Support;

use Adyen\Payment\Helper\SupportFormHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;

class ConfigurationSettingsForm extends Generic
{
    /**
     * @var SupportFormHelper
     */
    private $supportFormHelper;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param SupportFormHelper $supportFormHelper
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        SupportFormHelper $supportFormHelper
    ) {
        $this->supportFormHelper = $supportFormHelper;
        parent::__construct($context, $registry, $formFactory);
        $this->setActive(true);
    }

    /**
     * Prepare form before rendering HTML
     *
     * @return $this
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @throws LocalizedException
     */
    protected function _prepareForm(): ConfigurationSettingsForm
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

        $fieldset->addType(
            'textarea',
            \Adyen\Payment\Block\Adminhtml\Support\Form\Element\CustomTextareaElement::class
        );

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
                'placeholder' => __('Type a subject for your issue'),
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
                'class' => 'validate-emails',
                'required' => true,
                'readonly' => true,
                'value' => $this->supportFormHelper->getAdminEmail(),
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
                ['value' => 'Yes', 'label' => __('Yes')],
                ['value' => 'No', 'label' => __('No')]],
                'value' => 'No'
            ]
        )->setAfterElementHtml('
       <div class="tooltip">
       <span class="help">
       <span></span>
       </span>
       <div class="tooltip-content">
       Headless integration is when you use Adyen pre-configured backend but have a custom store frontend.
            </div>
       </div>');

        $fieldset->addType('file', \Adyen\Payment\Block\Adminhtml\Support\Form\Element\MultipleFileElement::class);
        $fieldset->addField(
            'attachments',
            'file',
            [
                'name' => 'attachments[]',
                'multiple' => 'multiple',
                'label' => __('Relevant logs & screenshots'),
                'title' => __('Relevant logs & screenshots'),
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
            'sendConfigurationValues',
            'radios',
            [
                'name' => 'sendConfigurationValues',
                'label' => __('Do you want to include plugin configuration values?'),
                'title' => __('Do you want to include plugin configuration values?'),
                'class' => '',
                'required' => false,
                'values' => [
                    ['value' => 1, 'label' => __('Yes')],
                    ['value' => 0, 'label' => __('No')]
                ],
                'value' => 1
            ]
        )->setAfterElementHtml('
       <div class="tooltip">
       <span class="help">
       <span></span>
       </span>
       <div class="tooltip-content">Automatically includes the configuration values in support email.
            </div>
       </div>');

        $fieldset->addField(
            'description',
            'textarea',
            [
                'name' => 'descriptionComments',
                'label' => __('Description'),
                'title' => __('Description'),
                'placeholder' => __('Tell us what is happening in  detail'),
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

    /**
     * @return string[]
     */
    public function getSupportTopics(): array
    {
        return $this->supportFormHelper->getSupportTopicsByFormType(
        SupportFormHelper::CONFIGURATION_SETTINGS_FORM
        );
    }

    /**
     * @return string[]
     */
    public function getIssuesTopics(): array
    {
        return $this->supportFormHelper->getIssuesTopicsByFormType(
            SupportFormHelper::CONFIGURATION_SETTINGS_FORM
        );
    }
}

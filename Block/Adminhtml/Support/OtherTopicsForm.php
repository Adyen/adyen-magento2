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

class OtherTopicsForm extends Generic
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
    )
    {
        $this->supportFormHelper = $supportFormHelper;
        parent::__construct($context, $registry, $formFactory);
    }

    /**
     * Prepare form before rendering HTML
     *
     * @return $this
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @throws LocalizedException
     */
    protected function _prepareForm(): OtherTopicsForm
    {
        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'support_form',
                'action' => $this->getUrl('adyen/support/othertopicsform'),
                'method' => 'post',
                'enctype' => 'multipart/form-data',
            ]
        ]);
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Other Topics')]);
        $this->_addElementTypes($fieldset);

        $fieldset->addType(
            'textarea',
            'Adyen\Payment\Block\Adminhtml\Support\Form\Element\CustomTextareaElement'
        );
        $fieldset->addType(
            'file',
            'Adyen\Payment\Block\Adminhtml\Support\Form\Element\MultipleFileElement'
        );

        $fieldset->addField(
            'subject',
            'text',
            [
                'name' => 'subject',
                'label' => __('What can we help you with?'),
                'title' => __('What can we help you with?'),
                'placeholder' => __('Select the topic you need help with'),
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
                'class' => 'adyen_support-form validate-emails',
                'required' => true,
                'value' => $this->supportFormHelper->getGeneralContactSenderEmail()
            ]
        );

        $fieldset->addField(
            'pspReference',
            'text',
            [
                'name' => 'pspReference',
                'label' => __('PSP Reference'),
                'title' => __('PSP Reference'),
                'class' => 'adyen_support-form',
                'required' => true
            ]
        )->setAfterElementHtml('
       <div class="tooltip">
       <span class="help">
       <span></span>
       </span>
       <div class="tooltip-content">This is Adyen’s unique 16-character to recognize a specific payment. 
       To find this information, go to Sales > Orders, and select an order. 
       The number will be listed in the comment history.
            </div>
       </div>');

        $fieldset->addField(
            'merchantReference',
            'text',
            [
                'name' => 'merchantReference',
                'label' => __('Merchant Reference'),
                'title' => __('Merchant Reference'),
                'class' => 'adyen_support-form',
                'required' => true,
            ]
        )->setAfterElementHtml('
       <div class="tooltip">
       <span class="help">
       <span></span>
       </span>
       <div class="tooltip-content">This is the reference for a specific payment. 
       To find this information, go to Sales > Orders, and select an order. 
       The number is top left corner.
            </div>
       </div>');

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
                    ['value' => 'Yes', 'label' => __('Yes')],
                    ['value' => 'No', 'label' => __('No')]
                ],
                'value' => 'No'
            ]
        )->setAfterElementHtml('
       <div class="tooltip">
       <span class="help">
       <span></span>
       </span>
       <div class="tooltip-content">Headless integration is when you use Adyen 
       pre-configured backend but have a custom store frontend.
            </div>
       </div>');

        $fieldset->addField(
            'terminalId',
            'text',
            [
                'name' => 'terminalId',
                'label' => __('POS terminal model & serial number'),
                'title' => __('POS terminal model & serial number'),
                'class' => 'adyen_support-form',
                'required' => false,
            ]
        )->setAfterElementHtml('
       <div class="tooltip">
       <span class="help">
       <span></span>
       </span>
       <div class="tooltip-content">This are the model and serial number of the device you used for this payment. 
       To find this information, go to Customer Area under Point of sale > Terminals.
            </div>
       </div>');

        $fieldset->addField(
            'attachments',
            'file',
            [
                'name' => 'attachments[]',
                'multiple'  => 'multiple',
                'label' => __('Relevant logs & screenshots'),
                'title' => __('Relevant logs & screenshots'),
                'class' => 'adyen_support-form',
                'required' => false,
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
                'name' => 'description',
                'label' => __('Describe your issue'),
                'title' => __('Describe your issue'),
                'placeholder' => __('Tell us what is happening in detail'),
                'class' => 'adyen_support-form',
                'required' => false,
            ]
        );

        $fieldset->addField(
            'submit_support_order_processing',
            'submit',
            [
                'name' => 'submit',
                'title' => __('Submit'),
                'class' => 'adyen_support-form primary',
                'value' => 'Submit'
            ]
        );

        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}

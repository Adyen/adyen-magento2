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

class OrderProcessingForm extends \Magento\Backend\Block\Widget\Form\Generic
{
    const HEADLESS_YES = 1;
    const HEADLESS_NO = 0;

    private $supportFormHelper;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
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
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareForm()
    {
        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'support_form',
                'action' => $this->getUrl('adyen/support/orderprocessingform'),
                'method' => 'post',
                'enctype' => 'multipart/form-data',
            ]
        ]);
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Order processing')]);
        $this->_addElementTypes($fieldset);

        $fieldset->addField(
            'topic',
            'select',
            [
                'name' => 'topic',
                'label' => __('Topic'),
                'title' => __('Topic'),
                'class' => '',
                'options' => [
                    'payment_status' => 'Payment status',
                    'failed_transaction' => 'Failed transaction',
                    'offer' => 'Offer',
                    'webhooks' => 'Notification &amp; webhooks',
                ],
                'required' => true,
                'value' => $this->getRequest()->getParam('topic'),
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
                'class' => 'validate-emails required',
                'required' => true,
                'value'=>$this->supportFormHelper->getGeneralContactSenderEmail()
            ]
        );
        $fieldset->addField(
            'pspReference',
            'text',
            [
                'name' => 'pspReference',
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
            'paymentMethod',
            'text',
            [
                'name' => 'paymentMethod',
                'label' => __('What payment method is causing the problem?'),
                'title' => __('What payment method is causing the problem?'),
                'class' => '',
                'required' => false,
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
        $fieldset->addType('file', 'Adyen\Payment\Block\Adminhtml\Support\Form\Element\MultipleFileElement');
        $fieldset->addField(
            'attachments',
            'file',
            [
                'name' => 'attachments[]',
                'multiple'  => 'multiple',
                'label' => __('Attachments'),
                'title' => __('Attachments'),
                'class' => '',
                'required' => false,
            ]
        );
        $fieldset->addField(
            'orderHistoryComments',
            'textarea',
            [
                'name' => 'orderHistoryComments',
                'label' => __('Order history comments'),
                'title' => __('Order history comments'),
                'class' => '',
                'required' => false,
            ]
        );
        $fieldset->addField(
            'orderDescription',
            'textarea',
            [
                'name' => 'orderDescription',
                'label' => __('Description'),
                'title' => __('Description'),
                'class' => '',
                'required' => false,
            ]
        );
        $fieldset->addField(
            'submit_support_order_processing',
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
}
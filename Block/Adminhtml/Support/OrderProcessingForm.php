<?php

namespace Adyen\Payment\Block\Adminhtml\Support;

class OrderProcessingForm extends \Magento\Backend\Block\Widget\Form\Generic
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
                'id' => 'support_form',
                'action' => $this->getUrl('adyen/support/orderprocessingform'),
                'method' => 'post',
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

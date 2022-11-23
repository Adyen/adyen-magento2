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
                'action' => $this->getData('action'),
                'method' => 'post',
            ]
        ]);
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Order processing')]);
        $this->_addElementTypes($fieldset);
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
            'upload_button',
            'button',
            [
                'name' => 'submit',

                'title' => __('click'),
                'class' => 'upload_button',
                'data_attribute' => '',
                'value' => 'Submit'
            ]
        );

        $this->setForm($form);
        return parent::_prepareForm();
    }
}

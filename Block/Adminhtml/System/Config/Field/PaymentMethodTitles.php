<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class PaymentMethodTitles extends AbstractFieldArray
{
    private ?PaymentMethodType $_paymentMethodTypeRenderer = null;

    /**
     * @return PaymentMethodType
     * @throws LocalizedException
     */
    protected function getPaymentMethodTypeRenderer(): PaymentMethodType
    {
        if (!$this->_paymentMethodTypeRenderer) {
            $this->_paymentMethodTypeRenderer = $this->getLayout()->createBlock(
                PaymentMethodType::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->_paymentMethodTypeRenderer;
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'payment_method_type',
            [
                'label'    => __('Payment Method'),
                'renderer' => $this->getPaymentMethodTypeRenderer(),
            ]
        );
        $this->addColumn(
            'title',
            [
                'label'    => __('Custom Title'),
                'renderer' => false,
            ]
        );

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Override');
    }

    /**
     * @param DataObject $row
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $paymentMethodType = $row->getPaymentMethodType();

        if ($paymentMethodType) {
            $options['option_' . $this->getPaymentMethodTypeRenderer()->calcOptionHash($paymentMethodType)]
                = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }
}

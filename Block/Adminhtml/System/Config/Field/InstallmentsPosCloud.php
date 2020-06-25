<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

class InstallmentsPosCloud extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{

    /**
     * @var \Adyen\Payment\Block\Adminhtml\System\Config\Field\Installment
     */
    protected $_installmentRenderer = null;

    /**
     * Return renderer for installments
     *
     * @return Installment|\Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getNumberOfInstallmentsRenderer()
    {
        if (!$this->_installmentRenderer) {
            $this->_installmentRenderer = $this->getLayout()->createBlock(
                \Adyen\Payment\Block\Adminhtml\System\Config\Field\Installment::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->_installmentRenderer;
    }

    /**
     * Prepare to render
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'amount',
            [
                'label' => __('Amount Range'),
                'renderer' => false,
            ]
        );
        $this->addColumn(
            'installments',
            [
                'label' => __('Number Of Installments'),
                'renderer' => $this->getNumberOfInstallmentsRenderer(),
            ]
        );

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Rule');
    }

    /**
     * Prepare existing row data object
     *
     * @param \Magento\Framework\DataObject $row
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $installments = $row->getInstallments();

        $options = [];
        if ($installments) {
            $options['option_' . $this->getNumberOfInstallmentsRenderer()->calcOptionHash($installments)]
                = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }
}

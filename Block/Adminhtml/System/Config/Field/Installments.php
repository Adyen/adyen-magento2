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

class Installments extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{

    /**
     * @var \Adyen\Payment\Block\Adminhtml\System\Config\Field\Installment
     */
    protected $_installmentRenderer = null;

    /**
     * @var \Magento\Braintree\Block\Adminhtml\Form\Field\CcTypes
     */
    protected $_ccTypesRenderer = null;

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
                '\Adyen\Payment\Block\Adminhtml\System\Config\Field\Installment',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->_installmentRenderer;
    }

    /**
     * Returns renderer for country element
     * 
     * @return \Magento\Braintree\Block\Adminhtml\Form\Field\Cctypes
     */
    protected function getCcTypesRenderer()
    {
        if (!$this->_ccTypesRenderer) {
            $this->_ccTypesRenderer = $this->getLayout()->createBlock(
                '\Adyen\Payment\Block\Adminhtml\System\Config\Field\Cctypes',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->_ccTypesRenderer;
    }

    /**
     * Prepare to render
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'amount',
            [
                'label'     => __('Amount Range (minor units)'),
                'renderer'  => false,
            ]
        );
        $this->addColumn(
            'installments',
            [
                'label'     => __('Max Number Of Installments'),
                'renderer'  => $this->getNumberOfInstallmentsRenderer(),
            ]
        );
        $this->addColumn(
            'cc_types',
            [
                'label' => __('Allowed Credit Card Types'),
                'renderer'  => $this->getCcTypesRenderer(),
            ]
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Rule');
    }

    /**
     * Prepare existing row data object
     *
     * @param \Magento\Framework\DataObject $row
     * @return void
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $installlments = $row->getInstallments();

        $options = [];
        if ($installlments) {
            
            $options['option_' . $this->getNumberOfInstallmentsRenderer()->calcOptionHash($installlments)]
                = 'selected="selected"';

            $ccTypes = $row->getCcTypes();
            if (!is_array($ccTypes)) {
                $ccTypes = [$ccTypes];
            }
            foreach ($ccTypes as $cardType) {
                $options['option_' . $this->getCcTypesRenderer()->calcOptionHash($cardType)]
                    = 'selected="selected"';
            }
        }
        $row->setData('option_extra_attrs', $options);
        return;
    }
}

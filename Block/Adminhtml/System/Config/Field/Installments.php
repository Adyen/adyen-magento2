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
     * @var \Adyen\Payment\Block\Adminhtml\System\Config\Field\Cctypes
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
                \Adyen\Payment\Block\Adminhtml\System\Config\Field\Installment::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->_installmentRenderer;
    }

    /**
     * Returns renderer for country element
     *
     * @return \Magento\Framework\View\Element\BlockInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getCcTypesRenderer()
    {
        if (!$this->_ccTypesRenderer) {
            $this->_ccTypesRenderer = $this->getLayout()->createBlock(
                \Adyen\Payment\Block\Adminhtml\System\Config\Field\Cctypes::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->_ccTypesRenderer;
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
        $this->addColumn(
            'cc_types',
            [
                'label' => __('Allowed Credit Card Types'),
                'renderer' => $this->getCcTypesRenderer(),
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
    }
}

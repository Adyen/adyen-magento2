<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

class Moto extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @var \Adyen\Payment\Block\Adminhtml\System\Config\Field\MotoEnviroment
     */
    protected $enviromentModeRenderer = null;

    /**
     * Prepare to render
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'merchant_account',
            ['label' => __('Merchant Account'), 'class' => 'required-entry', 'style' => 'width:130px']
        );
        $this->addColumn(
            'client_key',
            ['label' => __('Client Key'), 'class' => 'required-entry', 'style' => 'width:130px']
        );
        $this->addColumn(
            'api_key',
            [
                'label' => __('API Key'),
                'class' => 'required-entry',
                'style' => 'width:130px'
            ]
        );
        $this->addColumn(
            'demo_mode',
            [
                'label' => __('Test/Live Mode'),
                'renderer' => $this->getEnviromentModeRenderer()
            ]
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     * Return renderer for mode
     *
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getEnviromentModeRenderer()
    {
        if (!$this->enviromentModeRenderer) {
            $this->enviromentModeRenderer = $this->getLayout()->createBlock(
                \Adyen\Payment\Block\Adminhtml\System\Config\Field\MotoEnviroment::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->enviromentModeRenderer;
    }

    /**
     * Prepare existing row data object
     *
     * @param \Magento\Framework\DataObject $row
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $moto = $row->getData('demo_mode');
        $options = [];
        if ($moto) {
            $options['option_' . $this->getEnviromentModeRenderer()->calcOptionHash($moto)] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }
}
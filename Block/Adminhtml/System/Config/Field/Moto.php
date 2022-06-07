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
     * @var Enviroment
     */
    private $isProductionMode;
    /**
     * Prepare to render
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn('merchant_account', ['label' => __('Merchant Account'), 'class' => 'required-entry']);
        $this->addColumn('client_key', ['label' => __('Client Key'), 'class' => 'required-entry']);
        $this->addColumn('api_key', ['label' => __('API Key'), 'class' => 'required-entry']);
        $this->addColumn('enviroment', [
            'label' => __('Production Mode'),
            'renderer' => $this->getProductionMode()
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    private function getProductionMode()
    {
        if (!$this->isProductionMode) {
            $this->isProductionMode = $this->getLayout()->createBlock(
                \Adyen\Payment\Block\Adminhtml\System\Config\Field\MotoEnviroment::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->isProductionMode;
    }

}
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

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;

/**
 * Class RefusalReasonMapping
 *
 * @package Adyen\Payment\Block\Adminhtml\System\Config\Field
 */
class RefusalReasonMapping extends AbstractFieldArray
{
    protected ?RefusalReasonSelect $refusalReasonSelect = null;

    /**
     * @inheritDoc
     * @throws \Exception
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'refusal_reason',
            ['label' => __('Refusal Reason'), 'renderer' => $this->getRefusalReasonSelect()]
        );

        $this->addColumn(
            'value',
            ['label' => __('Value')]
        );

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Refusal Reason');
        parent::_prepareToRender();
    }

    /**
     * @throws \Exception
     * @return RefusalReasonSelect
     */
    private function getRefusalReasonSelect(): RefusalReasonSelect
    {
        if (!$this->refusalReasonSelect instanceof RefusalReasonSelect) {
            /** @var RefusalReasonSelect $countriesRenderer */
            $select = $this->getLayout()->createBlock(
                RefusalReasonSelect::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );

            $this->refusalReasonSelect = $select;
        }

        return $this->refusalReasonSelect;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $refusalReason = $row->getData('refusal_reason');
        if (is_string($refusalReason) && $refusalReason !== '') {
            $options['option_' . $this->getRefusalReasonSelect()->calcOptionHash($refusalReason)]
                = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }
}

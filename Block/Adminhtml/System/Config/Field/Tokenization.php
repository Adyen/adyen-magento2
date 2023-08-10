<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\Method\PaymentMethodInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Payment\Helper\Data;

class Tokenization extends AbstractFieldArray
{
    private Vault $vaultHelper;
    private Data $dataHelper;

    protected array $columns = [];
    protected ?SelectYesNo $enabledRenderer = null;
    protected ?InputReadonly $nameRenderer = null;
    protected ?InputHidden $paymentMethodCodeRenderer = null;
    protected ?RecurringProcessingModel $recurringProcessingModelRenderer = null;
    protected $_template = 'Adyen_Payment::config/token_type_table_array.phtml';

    public function __construct(
        Context $context,
        Vault $vaultHelper,
        Data $dataHelper,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);

        $this->vaultHelper = $vaultHelper;
        $this->dataHelper = $dataHelper;
    }

    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'name', [
                'label' => __('Payment Method'),
                'class' => 'required-entry',
                'style' => 'width:100%',
                'renderer' => $this->getNameRenderer()
            ]
        );
        $this->addColumn(
            'enabled', [
                'label' => __('Enabled'),
                'class' => 'required-entry',
                'style' => 'width:130px',
                'renderer' => $this->getEnabledRenderer()
            ]
        );
        $this->addColumn(
            'recurring_processing_model', [
                'label' => __('Recurring Processing Model'),
                'class' => 'required-entry',
                'style' => 'width:130px',
                'renderer' => $this->getRecurringProcessingModelRenderer()
            ]
        );
        $this->addColumn(
            'payment_method_code', [
                'label' => ' ',
                'style' => 'visibility: hidden',
                'class' => 'required-entry',
                'renderer' => $this->getPaymentMethodCodeRenderer()
            ]
        );
    }

    private function getEnabledRenderer(): SelectYesNo|BlockInterface
    {
        if (!$this->enabledRenderer) {
            $this->enabledRenderer = $this->getLayout()->createBlock(
                SelectYesNo::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->enabledRenderer;
    }

    private function getNameRenderer(): BlockInterface
    {
        if (!$this->nameRenderer) {
            $this->nameRenderer = $this->getLayout()->createBlock(
                InputReadonly::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->nameRenderer;
    }

    private function getPaymentMethodCodeRenderer(): BlockInterface
    {
        if (!$this->paymentMethodCodeRenderer) {
            $this->paymentMethodCodeRenderer = $this->getLayout()->createBlock(
                InputHidden::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->paymentMethodCodeRenderer;
    }

    private function getRecurringProcessingModelRenderer(): BlockInterface
    {
        if (!$this->recurringProcessingModelRenderer) {
            $this->recurringProcessingModelRenderer = $this->getLayout()->createBlock(
                RecurringProcessingModel::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->recurringProcessingModelRenderer;
    }

    protected function _prepareArrayRow(DataObject $row)
    {
        $options = [];
        $enabled = $row->getData('enabled');
        $recurringProcessingModel = $row->getData('recurring_processing_model');

        $paymentMethodCode = $row->getData('payment_method_code');
        $methodInstance = $this->dataHelper->getMethodInstance($paymentMethodCode);

        if ($enabled) {
            $options['option_' . $this->getEnabledRenderer()->calcOptionHash($enabled)] = 'selected=\"selected\"';
        }

        if ($recurringProcessingModel) {
            $options['option_' . $this->getRecurringProcessingModelRenderer()->calcOptionHash($recurringProcessingModel)] = 'selected=\"selected\"';
        }

        if ($methodInstance instanceof PaymentMethodInterface) {
            if (!$this->vaultHelper->paymentMethodSupportsRpm($methodInstance, Vault::SUBSCRIPTION)) {
                $options['option_' . $this->getRecurringProcessingModelRenderer()->calcOptionHash(Vault::SUBSCRIPTION)] = 'disabled';
            }

            if (!$this->vaultHelper->paymentMethodSupportsRpm($methodInstance, Vault::UNSCHEDULED_CARD_ON_FILE)) {
                $options['option_' . $this->getRecurringProcessingModelRenderer()->calcOptionHash(Vault::UNSCHEDULED_CARD_ON_FILE)] = 'disabled';
            }

            if (!$this->vaultHelper->paymentMethodSupportsRpm($methodInstance, Vault::CARD_ON_FILE)) {
                $options['option_' . $this->getRecurringProcessingModelRenderer()->calcOptionHash(Vault::CARD_ON_FILE)] = 'disabled';
            }
        }

        $row->setData('option_extra_attrs', $options);
    }
}

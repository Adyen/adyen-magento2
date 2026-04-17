<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

use Adyen\Payment\Model\Config\Source\PaymentMethodType as PaymentMethodTypeSource;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class PaymentMethodType extends Select
{
    /**
     * @param Context $context
     * @param PaymentMethodTypeSource $source
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly PaymentMethodTypeSource $source,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            foreach ($this->source->toOptionArray() as $option) {
                $this->addOption($option['value'], (string) $option['label']);
            }
        }

        return parent::_toHtml();
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setInputName(string $value): self
    {
        return $this->setName($value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }
}

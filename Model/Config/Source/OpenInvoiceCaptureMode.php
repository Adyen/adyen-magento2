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

namespace Adyen\Payment\Model\Config\Source;

use Adyen\Payment\Helper\Data;
use Magento\Framework\Option\ArrayInterface;

class OpenInvoiceCaptureMode implements ArrayInterface
{
    protected Data $adyenHelper;

    public function __construct(
        Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    public function toOptionArray(): array
    {
        $recurringTypes = $this->adyenHelper->getOpenInvoiceCaptureModes();

        foreach ($recurringTypes as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }

        return $options;
    }
}

<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

use Adyen\Payment\Helper\Data;
use Magento\Framework\Data\OptionSourceInterface;

class CaptureMode implements OptionSourceInterface
{
    const CAPTURE_MODE_MANUAL = 'manual';
    const CAPTURE_MODE_AUTO = 'auto';
    const CAPTURE_MODE_ONSHIPMENT = 'onshipment';

    /**
     * CaptureMode constructor.
     *
     * @param Data $adyenHelper
     */
    public function __construct(
        protected readonly Data $adyenHelper
    ) { }

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        $captureModes = $this->adyenHelper->getCaptureModes();

        foreach ($captureModes as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options ?? [];
    }
}

<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Model\MethodInterface;

class PaymentAction implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => MethodInterface::ACTION_AUTHORIZE, 'label' => MethodInterface::ACTION_AUTHORIZE],
            ['value' => MethodInterface::ACTION_ORDER, 'label' => MethodInterface::ACTION_ORDER],
        ];
    }

}

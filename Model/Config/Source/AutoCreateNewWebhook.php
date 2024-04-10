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

use Magento\Framework\Data\OptionSourceInterface;

class AutoCreateNewWebhook implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '0', 'label' => 'Yes, I want to use my own username and password'],
            ['value' => '1', 'label' => 'No, generate new username and password for me']
        ];
    }
}

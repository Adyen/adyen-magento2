<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\Support;

use Magento\Backend\Block\Page;

class ConfigurationSettings extends Page implements SupportTabInterface
{
    public function getSupportTopics(): array
    {
        return [
            'required_settings' => 'Required settings',
            'card_payments' => 'Card payments',
            'card_tokenization' => 'Card tokenization',
            'alt_payment_methods' => 'Alternative payment methods',
            'pos_integration' => 'POS integration with cloud',
            'pay_by_link' => 'Pay By Link',
            'adyen_giving' => 'Adyen Giving',
            'advanced_settings' => 'Advanced settings',
        ];
    }

    public function supportFormUrl()
    {
        return $this->getUrl('adyen/support/configurationsettingsform');
    }

    public function getPageTitle()
    {
        return __("Configuration Settings");
    }
}

<?php

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
}

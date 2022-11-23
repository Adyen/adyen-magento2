<?php

namespace Adyen\Payment\Block\Adminhtml\Support;

class ConfigurationSettingsForm extends \Magento\Backend\Block\Page
{
    public function getSupportTopics(): array
    {
        return [
            'required_settings' => 'Required Settings',
            'card_payments' => 'Card payments',
            'card_tokenization' => 'Card tokenization',
            'alt_payment_methods' => 'Alternative payment methods',
            'pos_integration' => 'POS integration with cloud',
            'pay_by_link' => 'Pay By Link',
            'adyen_giving' => 'Adyen Giving',
            'advanced_settings' => 'Advanced settings'
        ];
    }

    public function getIssuesTopics(): array
    {
        return [
            'invalid_origin' => 'Invalid Origin',
            'headles_state_data_actions' => 'Headless state data actions',
            'refund' => 'Refund',
            'other' => 'Other'
        ];
    }
}

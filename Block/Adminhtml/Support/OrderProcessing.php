<?php

namespace Adyen\Payment\Block\Adminhtml\Support;

use Magento\Backend\Block\Page;

class OrderProcessing extends Page implements SupportTabInterface
{
    public function getSupportTopics(): array
    {
        return [
            'payment_status' => 'Payment status',
            'failed_transaction' => 'Failed transaction',
            'offer' => 'Offer',
            'webhooks' => 'Notification &amp; webhooks',
        ];
    }

    public function supportFormUrl()
    {
        return $this->getUrl('adyen/support/orderprocessingform');
    }
}

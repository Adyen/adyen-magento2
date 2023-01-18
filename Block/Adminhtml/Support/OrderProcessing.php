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

    public function getPageTitle()
    {
        return __("Order Processing");
    }
}

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

namespace Adyen\Payment\Block\Adminhtml\Notification;

use Adyen\Payment\Helper\Config;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Notices;

class WebhookRemovalJobNotice extends Notices
{
    public function __construct(
        Context $context,
        private readonly Config $configHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return bool
     */
    public function isProcessedWebhookRemovalEnabled(): bool
    {
        return $this->configHelper->getIsProcessedWebhookRemovalEnabled();
    }

    /**
     * Returns the number of days after which the notifications will be cleaned-up.
     *
     * @return int
     */
    public function getNumberOfDays(): int
    {
        return $this->configHelper->getProcessedWebhookRemovalTime();
    }
}

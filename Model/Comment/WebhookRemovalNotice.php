<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Comment;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\ResourceModel\Notification\Collection;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;
use Magento\Config\Model\Config\CommentInterface;

class WebhookRemovalNotice implements CommentInterface
{
    public function __construct(
        private readonly CollectionFactory $notificationCollectionFactory,
        private readonly Config $configHelper
    ) { }

    public function getCommentText($elementValue)
    {
        if ($elementValue === '0') {
            /** @var Collection $notificationCollection */
            $notificationCollection = $this->notificationCollectionFactory->create();
            $notificationCollection->getProcessedWebhookIdsByTimeLimit(
                $this->configHelper->getProcessedWebhookRemovalTime()
            );

            return __(
                'Enabling this feature will remove %1 processed webhooks from the database!',
                $notificationCollection->getSize()
            );
        }
    }
}

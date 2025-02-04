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

use Adyen\Payment\Cron\Providers\ProcessedWebhooksProvider;
use Magento\Config\Model\Config\CommentInterface;

class WebhookRemovalNotice implements CommentInterface
{
    public function __construct(
        private readonly ProcessedWebhooksProvider $processedNotificationProvider
    ) { }

    public function getCommentText($elementValue)
    {
        if ($elementValue === '0') {
            $numberOfNotificationsToBeRemoved = count($this->processedNotificationProvider->provide());

            return __(
                'Enabling this feature will remove %1 processed webhooks from the database!',
                $numberOfNotificationsToBeRemoved
            );
        }
    }
}

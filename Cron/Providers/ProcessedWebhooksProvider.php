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

namespace Adyen\Payment\Cron\Providers;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\ResourceModel\Notification\Collection;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;

class ProcessedWebhooksProvider implements WebhooksProviderInterface
{
    /**
     * @param CollectionFactory $notificationCollectionFactory
     * @param Config $configHelper
     */
    public function __construct(
        private readonly CollectionFactory $notificationCollectionFactory,
        private readonly Config $configHelper
    ) { }

    /**
     * Provides the `entity_id`s of the processed webhooks limited by the removal time
     *
     * @return array
     */
    public function provide(): array
    {
        $numberOfDays = $this->configHelper->getProcessedWebhookRemovalTime();

        /** @var Collection $notificationCollection */
        $notificationCollection = $this->notificationCollectionFactory->create();
        $notificationCollection->getProcessedWebhookIdsByTimeLimit($numberOfDays, self::BATCH_SIZE);

        if ($notificationCollection->getSize() > 0) {
            return $notificationCollection->getColumnValues('entity_id');
        } else {
            return [];
        }
    }

    /**
     * Returns the provider name
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return "Adyen processed webhooks provider";
    }
}

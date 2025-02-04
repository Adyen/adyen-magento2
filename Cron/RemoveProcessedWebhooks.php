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

namespace Adyen\Payment\Cron;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Cron\Providers\WebhooksProviderInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Exception;

class RemoveProcessedWebhooks
{
    /**
     * @param WebhooksProviderInterface[] $providers
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param AdyenNotificationRepositoryInterface $adyenNotificationRepository
     */
    public function __construct(
        private readonly array $providers,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly AdyenNotificationRepositoryInterface $adyenNotificationRepository
    ) { }

    /**
     * @return void
     */
    public function execute(): void
    {
        $isWebhookCleanupEnabled = $this->configHelper->getIsProcessedWebhookRemovalEnabled();

        if ($isWebhookCleanupEnabled === true) {
            $numberOfItemsRemoved = 0;

            foreach ($this->providers as $provider) {
                /** @var Notification $notificationToCleanup */
                foreach ($provider->provide() as $notificationToCleanup) {
                    try {
                        $isSuccessfullyDeleted = $this->adyenNotificationRepository->delete($notificationToCleanup);

                        if ($isSuccessfullyDeleted) {
                            $message = __(
                                '%1: Notification with entity_id %2 has been deleted because it was processed %3 days ago.',
                                $provider->getProviderName(),
                                $notificationToCleanup->getEntityId(),
                                $this->configHelper->getProcessedWebhookRemovalTime()
                            );
                            $this->adyenLogger->addAdyenNotification($message);

                            $numberOfItemsRemoved++;
                        }
                    } catch (Exception $e) {
                        $message = __(
                            '%1: An error occurred while deleting the notification with entity_id %2: %3',
                            $provider->getProviderName(),
                            $notificationToCleanup->getEntityId(),
                            $e->getMessage()
                        );

                        $this->adyenLogger->error($message);
                    }
                }
            }

            $successMessage = __(
                '%1 processed webhooks have been removed by the RemoveProcessedWebhooks cronjob.',
                $numberOfItemsRemoved
            );
            $this->adyenLogger->addAdyenNotification($successMessage);
        } else {
            $message = __('Processed webhook removal feature is disabled. The cronjob has been skipped!');
            $this->adyenLogger->addAdyenDebug($message);
        }
    }
}

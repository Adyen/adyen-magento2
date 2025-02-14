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
                $webhookIdsToRemove = $provider->provide();
                $numberOfWebhooksProvided = count($webhookIdsToRemove);

                if ($numberOfWebhooksProvided > 0) {
                    try {
                        $this->adyenNotificationRepository->deleteByIds($webhookIdsToRemove);
                        $numberOfItemsRemoved += $numberOfWebhooksProvided;
                    } catch (Exception $e) {
                        $message = __(
                            '%1: An error occurred while deleting webhooks! %2',
                            $provider->getProviderName(),
                            $e->getMessage()
                        );

                        $this->adyenLogger->error($message);
                    }
                }
            }

            if ($numberOfItemsRemoved > 0) {
                $successMessage = __(
                    '%1 processed webhooks have been removed by the RemoveProcessedWebhooks cronjob.',
                    $numberOfItemsRemoved
                );

                $this->adyenLogger->addAdyenNotification($successMessage);
            } else {
                $debugMessage = __(
                    'There is no webhooks to be removed by RemoveProcessedWebhooks cronjob.',
                    $numberOfItemsRemoved
                );

                $this->adyenLogger->addAdyenDebug($debugMessage);
            }
        } else {
            $message = __('Processed webhook removal feature is disabled. The cronjob has been skipped!');
            $this->adyenLogger->addAdyenDebug($message);
        }
    }
}

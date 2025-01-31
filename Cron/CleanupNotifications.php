<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Cron\Providers\NotificationsProviderInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class CleanupNotifications
{
    /**
     * @param NotificationsProviderInterface[] $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly AdyenLogger $adyenLogger,
        private readonly Config $configHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdyenNotificationRepositoryInterface $adyenNotificationRepository
    ) { }

    /**
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(): void
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isWebhookCleanupEnabled = $this->configHelper->getIsWebhookCleanupEnabled($storeId);

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
                                $this->configHelper->getRequiredDaysForOldWebhooks($storeId)
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

            $successMessage = sprintf(
                __('%s webhook notifications have been cleaned-up by the CleanupNotifications job.'),
                $numberOfItemsRemoved
            );
            $this->adyenLogger->addAdyenDebug($successMessage);
        } else {
            $message = __('Webhook notification clean-up feature is disabled. The job has been skipped!');
            $this->adyenLogger->addAdyenDebug($message);
        }
    }
}

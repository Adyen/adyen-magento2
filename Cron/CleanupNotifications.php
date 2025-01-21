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

        if ($isWebhookCleanupEnabled) {
            $numberOfItemsRemoved = 0;

            foreach ($this->providers as $provider) {
                /** @var Notification $notificationToCleanup */
                foreach ($provider->provide() as $notificationToCleanup) {
                    $isSuccessfullyDeleted = $this->adyenNotificationRepository->delete($notificationToCleanup);

                    if ($isSuccessfullyDeleted) {
                        $message = __('%1: Notification with entityId %2 has been deleted.',
                            $provider->getProviderName(), $notificationToCleanup->getEntityId());
                        $this->adyenLogger->addAdyenNotification($message);

                        $numberOfItemsRemoved++;
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

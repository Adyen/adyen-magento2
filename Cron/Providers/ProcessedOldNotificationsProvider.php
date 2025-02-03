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

namespace Adyen\Payment\Cron\Providers;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;

class ProcessedOldNotificationsProvider implements NotificationsProviderInterface
{
    public function __construct(
        private readonly AdyenNotificationRepositoryInterface $adyenNotificationRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly Config $configHelper,
        private readonly AdyenLogger $adyenLogger
    ) { }

    public function provide(): array
    {
        $numberOfDays = $this->configHelper->getRequiredDaysForOldWebhooks();

        $dateFrom = date('Y-m-d H:i:s', time() - $numberOfDays * 24 * 60 * 60);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('done', 1)
            ->addFilter('processing', 0)
            ->addFilter('created_at', $dateFrom, 'lteq')
            ->create();

        try {
            $items = $this->adyenNotificationRepository->getList($searchCriteria);
            return $items->getItems();
        } catch (LocalizedException $e) {
            $errorMessage = sprintf(
                __('An error occurred while providing notifications older than %s days!'),
                $numberOfDays
            );

            $this->adyenLogger->error($errorMessage);

            return [];
        }
    }

    public function getProviderName(): string
    {
        return "Adyen processed old webhook notifications";
    }
}

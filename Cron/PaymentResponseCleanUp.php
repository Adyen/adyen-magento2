<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\PaymentResponse as PaymentResponseResourceModel;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\CollectionFactory as PaymentResponseCollectionFactory;
use Exception;

/**
 * Cleans up stale rows from the `adyen_payment_response` table.
 *
 * The table stores the raw /payments API response and is only consumed by the
 * multishipping success page to resume pending action components. Once an order
 * is finalized (complete, closed or canceled) or is orphaned (older than the
 * grace period with no associated Magento order), the row is safe to delete.
 *
 * Feature is OFF by default and gated by `payment/adyen_abstract/clean_adyen_payment_response`.
 */
class PaymentResponseCleanUp
{
    /**
     * Maximum number of rows processed per step per cron run.
     */
    const BATCH_SIZE = 1000;

    /**
     * Grace period (in days) before an orphan row becomes eligible for deletion.
     */
    const ORPHAN_GRACE_DAYS = 1;

    /**
     * @param PaymentResponseCollectionFactory $paymentResponseCollectionFactory
     * @param PaymentResponseResourceModel $paymentResponseResourceModel
     * @param Config $configHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly PaymentResponseCollectionFactory $paymentResponseCollectionFactory,
        private readonly PaymentResponseResourceModel $paymentResponseResourceModel,
        private readonly Config $configHelper,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @return void
     */
    public function execute(): void
    {
        if (!$this->configHelper->getIsPaymentResponseCleanupEnabled()) {
            $this->adyenLogger->addAdyenDebug(
                'Adyen payment response cleanup feature is disabled. The cronjob has been skipped!'
            );
            return;
        }

        $finalizedRemoved = $this->deleteBatch(
            'finalized',
            fn (): array => $this->paymentResponseCollectionFactory->create()
                ->getFinalizedPaymentResponseIds(self::BATCH_SIZE)
        );

        $orphanRemoved = $this->deleteBatch(
            'orphan',
            fn (): array => $this->paymentResponseCollectionFactory->create()
                ->getOrphanPaymentResponseIds(self::ORPHAN_GRACE_DAYS, self::BATCH_SIZE)
        );

        $totalRemoved = $finalizedRemoved + $orphanRemoved;

        if ($totalRemoved > 0) {
            $this->adyenLogger->addAdyenNotification(
                (string) __(
                    '%1 Adyen payment response row(s) have been removed by the PaymentResponseCleanUp cronjob (%2 finalized, %3 orphan).',
                    $totalRemoved,
                    $finalizedRemoved,
                    $orphanRemoved
                )
            );
        } else {
            $this->adyenLogger->addAdyenDebug(
                'There are no Adyen payment response rows to be removed by PaymentResponseCleanUp cronjob.'
            );
        }
    }

    /**
     * Fetches a batch of IDs via the supplied provider and deletes them in a single query.
     *
     * @param string $stepName Used only for error logging.
     * @param callable $idProvider Returns the array of `entity_id`s to delete.
     * @return int Number of rows removed in this step (0 on failure).
     */
    private function deleteBatch(string $stepName, callable $idProvider): int
    {
        try {
            $ids = $idProvider();
        } catch (Exception $e) {
            $this->adyenLogger->error(
                (string) __(
                    'PaymentResponseCleanUp (%1): failed to fetch ids for deletion. %2',
                    $stepName,
                    $e->getMessage()
                )
            );
            return 0;
        }

        if (empty($ids)) {
            return 0;
        }

        try {
            $this->paymentResponseResourceModel->deleteByIds($ids);
            return count($ids);
        } catch (Exception $e) {
            $this->adyenLogger->error(
                (string) __(
                    'PaymentResponseCleanUp (%1): an error occurred while deleting payment responses. %2',
                    $stepName,
                    $e->getMessage()
                )
            );
            return 0;
        }
    }
}

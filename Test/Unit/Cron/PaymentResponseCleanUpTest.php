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

namespace Adyen\Payment\Test\Unit\Cron;

use Adyen\Payment\Cron\PaymentResponseCleanUp;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\PaymentResponse as PaymentResponseResourceModel;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection as PaymentResponseCollection;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\CollectionFactory as PaymentResponseCollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DB\Adapter\DeadlockException;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentResponseCleanUpTest extends AbstractAdyenTestCase
{
    protected ?PaymentResponseCleanUp $cron;
    protected PaymentResponseCollectionFactory|MockObject $collectionFactoryMock;
    protected PaymentResponseCollection|MockObject $collectionMock;
    protected PaymentResponseResourceModel|MockObject $resourceModelMock;
    protected Config|MockObject $configHelperMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;

    protected function setUp(): void
    {
        $this->collectionMock = $this->createMock(PaymentResponseCollection::class);
        $this->collectionFactoryMock = $this->createGeneratedMock(
            PaymentResponseCollectionFactory::class,
            ['create']
        );
        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);

        $this->resourceModelMock = $this->createMock(PaymentResponseResourceModel::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->cron = new PaymentResponseCleanUp(
            $this->collectionFactoryMock,
            $this->resourceModelMock,
            $this->configHelperMock,
            $this->adyenLoggerMock
        );
    }

    protected function tearDown(): void
    {
        $this->cron = null;
    }

    public function testExecuteConfigDisabled()
    {
        $this->configHelperMock->expects($this->once())
            ->method('getIsPaymentResponseCleanupEnabled')
            ->willReturn(false);

        $this->collectionMock->expects($this->never())->method('getFinalizedPaymentResponseIds');
        $this->collectionMock->expects($this->never())->method('getOrphanPaymentResponseIds');
        $this->resourceModelMock->expects($this->never())->method('deleteByIds');

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenDebug');

        $this->cron->execute();
    }

    public function testExecuteEnabledWithNoRows()
    {
        $this->configHelperMock->expects($this->once())
            ->method('getIsPaymentResponseCleanupEnabled')
            ->willReturn(true);

        $this->collectionMock->expects($this->once())
            ->method('getFinalizedPaymentResponseIds')
            ->with(PaymentResponseCleanUp::BATCH_SIZE)
            ->willReturn([]);

        $this->collectionMock->expects($this->once())
            ->method('getOrphanPaymentResponseIds')
            ->with(PaymentResponseCleanUp::ORPHAN_GRACE_DAYS, PaymentResponseCleanUp::BATCH_SIZE)
            ->willReturn([]);

        $this->resourceModelMock->expects($this->never())->method('deleteByIds');
        $this->adyenLoggerMock->expects($this->once())->method('addAdyenDebug');
        $this->adyenLoggerMock->expects($this->never())->method('addAdyenNotification');

        $this->cron->execute();
    }

    public function testExecuteFinalizedOnly()
    {
        $finalizedIds = [1, 2, 3];

        $this->configHelperMock->expects($this->once())
            ->method('getIsPaymentResponseCleanupEnabled')
            ->willReturn(true);

        $this->collectionMock->expects($this->once())
            ->method('getFinalizedPaymentResponseIds')
            ->with(PaymentResponseCleanUp::BATCH_SIZE)
            ->willReturn($finalizedIds);

        $this->collectionMock->expects($this->once())
            ->method('getOrphanPaymentResponseIds')
            ->willReturn([]);

        $this->resourceModelMock->expects($this->once())
            ->method('deleteByIds')
            ->with($finalizedIds);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->cron->execute();
    }

    public function testExecuteOrphanOnly()
    {
        $orphanIds = [10, 20];

        $this->configHelperMock->expects($this->once())
            ->method('getIsPaymentResponseCleanupEnabled')
            ->willReturn(true);

        $this->collectionMock->expects($this->once())
            ->method('getFinalizedPaymentResponseIds')
            ->willReturn([]);

        $this->collectionMock->expects($this->once())
            ->method('getOrphanPaymentResponseIds')
            ->with(PaymentResponseCleanUp::ORPHAN_GRACE_DAYS, PaymentResponseCleanUp::BATCH_SIZE)
            ->willReturn($orphanIds);

        $this->resourceModelMock->expects($this->once())
            ->method('deleteByIds')
            ->with($orphanIds);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->cron->execute();
    }

    public function testExecuteFinalizedAndOrphan()
    {
        $finalizedIds = [1, 2, 3];
        $orphanIds = [10, 20];

        $this->configHelperMock->expects($this->once())
            ->method('getIsPaymentResponseCleanupEnabled')
            ->willReturn(true);

        $this->collectionMock->expects($this->once())
            ->method('getFinalizedPaymentResponseIds')
            ->willReturn($finalizedIds);

        $this->collectionMock->expects($this->once())
            ->method('getOrphanPaymentResponseIds')
            ->willReturn($orphanIds);

        $this->resourceModelMock->expects($this->exactly(2))
            ->method('deleteByIds')
            ->willReturnCallback(function (array $ids) use ($finalizedIds, $orphanIds) {
                $this->assertTrue($ids === $finalizedIds || $ids === $orphanIds);
            });

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->cron->execute();
    }

    public function testExecuteFinalizedDeletionExceptionDoesNotBlockOrphan()
    {
        $finalizedIds = [1, 2, 3];
        $orphanIds = [10, 20];

        $this->configHelperMock->expects($this->once())
            ->method('getIsPaymentResponseCleanupEnabled')
            ->willReturn(true);

        $this->collectionMock->expects($this->once())
            ->method('getFinalizedPaymentResponseIds')
            ->willReturn($finalizedIds);

        $this->collectionMock->expects($this->once())
            ->method('getOrphanPaymentResponseIds')
            ->willReturn($orphanIds);

        $this->resourceModelMock->expects($this->exactly(2))
            ->method('deleteByIds')
            ->willReturnCallback(function (array $ids) use ($finalizedIds) {
                if ($ids === $finalizedIds) {
                    throw new DeadlockException();
                }
            });

        $this->adyenLoggerMock->expects($this->once())->method('error');
        // Orphan step still succeeds (2 rows), so we get a success notification.
        $this->adyenLoggerMock->expects($this->once())->method('addAdyenNotification');

        $this->cron->execute();
    }

    public function testExecuteProviderExceptionIsLoggedAndSwallowed()
    {
        $this->configHelperMock->expects($this->once())
            ->method('getIsPaymentResponseCleanupEnabled')
            ->willReturn(true);

        $this->collectionMock->expects($this->once())
            ->method('getFinalizedPaymentResponseIds')
            ->willThrowException(new \RuntimeException('boom'));

        $this->collectionMock->expects($this->once())
            ->method('getOrphanPaymentResponseIds')
            ->willReturn([]);

        $this->resourceModelMock->expects($this->never())->method('deleteByIds');
        $this->adyenLoggerMock->expects($this->once())->method('error');
        $this->adyenLoggerMock->expects($this->once())->method('addAdyenDebug');

        $this->cron->execute();
    }
}

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

namespace Adyen\Payment\Test\Cron;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Cron\CleanupNotifications;
use Adyen\Payment\Cron\Providers\NotificationsProviderInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class CleanupNotificationsTest extends AbstractAdyenTestCase
{
    protected ?CleanupNotifications $cleanupNotifications;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected Config|MockObject $configHelperMock;
    protected StoreManagerInterface|MockObject $storeManagerMock;
    protected AdyenNotificationRepositoryInterface|MockObject  $adyenNotificationRepositoryMock;
    protected NotificationsProviderInterface|MockObject $notificationsProvider;
    protected array $providers;

    const STORE_ID = PHP_INT_MAX;

    protected function setUp(): void
    {
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->adyenNotificationRepositoryMock =
            $this->createMock(AdyenNotificationRepositoryInterface::class);
        $this->notificationsProvider = $this->createMock(NotificationsProviderInterface::class);

        $this->providers[] = $this->notificationsProvider;

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->cleanupNotifications = new CleanupNotifications(
            $this->providers,
            $this->adyenLoggerMock,
            $this->configHelperMock,
            $this->storeManagerMock,
            $this->adyenNotificationRepositoryMock
        );
    }

    protected function tearDown(): void
    {
        $this->cleanupNotifications = null;
    }

    public function testExecuteConfigEnabled()
    {
        $this->configHelperMock->expects($this->once())
            ->method('getIsWebhookCleanupEnabled')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $notificationMock = $this->createMock(Notification::class);
        $providerResult[] = $notificationMock;

        $this->notificationsProvider->method('provide')->willReturn($providerResult);

        $this->adyenNotificationRepositoryMock->expects($this->once())
            ->method('delete')
            ->with($notificationMock)
            ->willReturn(true);

        $this->adyenLoggerMock->expects($this->once())->method('addAdyenDebug');

        $this->cleanupNotifications->execute();
    }

    public function testExecuteConfigDisabled()
    {
        $this->configHelperMock->expects($this->once())
            ->method('getIsWebhookCleanupEnabled')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $this->notificationsProvider->expects($this->never())->method('provide');
        $this->adyenNotificationRepositoryMock->expects($this->never())->method('delete');
        $this->adyenLoggerMock->expects($this->once())->method('addAdyenDebug');

        $this->cleanupNotifications->execute();
    }
}

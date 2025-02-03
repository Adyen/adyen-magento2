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
use Adyen\Payment\Cron\RemoveProcessedWebhooks;
use Adyen\Payment\Cron\Providers\WebhooksProviderInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DB\Adapter\DeadlockException;
use PHPUnit\Framework\MockObject\MockObject;

class RemoveProcessedWebhooksTest extends AbstractAdyenTestCase
{
    protected ?RemoveProcessedWebhooks $cleanupNotifications;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected Config|MockObject $configHelperMock;
    protected AdyenNotificationRepositoryInterface|MockObject  $adyenNotificationRepositoryMock;
    protected WebhooksProviderInterface|MockObject $notificationsProvider;
    protected array $providers;

    protected function setUp(): void
    {
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->adyenNotificationRepositoryMock =
            $this->createMock(AdyenNotificationRepositoryInterface::class);
        $this->notificationsProvider = $this->createMock(WebhooksProviderInterface::class);

        $this->providers[] = $this->notificationsProvider;

        $this->cleanupNotifications = new RemoveProcessedWebhooks(
            $this->providers,
            $this->adyenLoggerMock,
            $this->configHelperMock,
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
            ->method('getIsProcessedWebhookRemovalEnabled')
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
            ->method('getIsProcessedWebhookRemovalEnabled')
            ->willReturn(false);

        $this->notificationsProvider->expects($this->never())->method('provide');
        $this->adyenNotificationRepositoryMock->expects($this->never())->method('delete');
        $this->adyenLoggerMock->expects($this->once())->method('addAdyenDebug');

        $this->cleanupNotifications->execute();
    }

    public function testExecuteException()
    {
        $this->configHelperMock->expects($this->once())
            ->method('getIsProcessedWebhookRemovalEnabled')
            ->willReturn(true);

        $notificationMock = $this->createMock(Notification::class);
        $providerResult[] = $notificationMock;

        $this->notificationsProvider->method('provide')->willReturn($providerResult);

        $this->adyenNotificationRepositoryMock->expects($this->once())
            ->method('delete')
            ->with($notificationMock)
            ->willThrowException(new DeadlockException());

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $this->cleanupNotifications->execute();
    }
}

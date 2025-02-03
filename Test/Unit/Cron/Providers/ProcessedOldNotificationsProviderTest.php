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

namespace Adyen\Payment\Test\Cron\Providers;

use Adyen\Payment\Api\Data\NotificationInterface;
use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Cron\Providers\ProcessedWebhooksProvider;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;

class ProcessedNotificationsProviderTest extends AbstractAdyenTestCase
{
    protected ?ProcessedWebhooksProvider $notificationsProvider;
    protected AdyenNotificationRepositoryInterface|MockObject $adyenNotificationRepositoryMock;
    protected SearchCriteriaBuilder|MockObject $searchCriteriaBuilderMock;
    protected Config|MockObject $configHelperMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;

    protected function setUp(): void
    {
        $this->adyenNotificationRepositoryMock =
            $this->createMock(AdyenNotificationRepositoryInterface::class);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->notificationsProvider = new ProcessedWebhooksProvider(
            $this->adyenNotificationRepositoryMock,
            $this->searchCriteriaBuilderMock,
            $this->configHelperMock,
            $this->adyenLoggerMock
        );
    }

    protected function tearDown(): void
    {
        $this->notificationsProvider = null;
    }

    public function testProvideSuccess()
    {
        $expiryDays = 90;

        $this->configHelperMock->expects($this->once())
            ->method('getProcessedWebhookRemovalTime')
            ->willReturn($expiryDays);

        $dateMock = date('Y-m-d H:i:s', time() - $expiryDays * 24 * 60 * 60);

        $this->searchCriteriaBuilderMock->expects($this->exactly(3))
            ->method('addFilter')
            ->withConsecutive(
                ['done', 1, 'eq'],
                ['processing', 0, 'eq'],
                ['created_at', $dateMock, 'lteq']
            )
            ->willReturnSelf();

        $searchCriteriaMock = $this->createMock(SearchCriteria::class);

        $this->searchCriteriaBuilderMock->expects($this->once())
            ->method('create')
            ->willReturn($searchCriteriaMock);

        $searchResults[] = $this->createMock(NotificationInterface::class);

        $searchResultsMock = $this->createMock(SearchResultsInterface::class);
        $searchResultsMock->expects($this->once())
            ->method('getItems')
            ->willReturn($searchResults);

        $this->adyenNotificationRepositoryMock->expects($this->once())
            ->method('getList')
            ->with($searchCriteriaMock)
            ->willReturn($searchResultsMock);

        $result = $this->notificationsProvider->provide();
        $this->assertIsArray($result);
        $this->assertInstanceOf(NotificationInterface::class, $result[0]);
    }

    public function testProvideFailure()
    {
        $expiryDays = 90;

        $this->configHelperMock->expects($this->once())
            ->method('getProcessedWebhookRemovalTime')
            ->willReturn($expiryDays);

        $dateMock = date('Y-m-d H:i:s', time() - $expiryDays * 24 * 60 * 60);

        $this->searchCriteriaBuilderMock->expects($this->exactly(3))
            ->method('addFilter')
            ->withConsecutive(
                ['done', 1, 'eq'],
                ['processing', 0, 'eq'],
                ['created_at', $dateMock, 'lteq']
            )
            ->willReturnSelf();

        $searchCriteriaMock = $this->createMock(SearchCriteria::class);

        $this->searchCriteriaBuilderMock->expects($this->once())
            ->method('create')
            ->willReturn($searchCriteriaMock);

        $this->adyenNotificationRepositoryMock->expects($this->once())
            ->method('getList')
            ->willThrowException(new LocalizedException(__('mock error message')));

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $result = $this->notificationsProvider->provide();
        $this->assertEmpty($result);
    }

    public function testGetProviderName()
    {
        $this->assertEquals(
            'Adyen processed old webhook notifications',
            $this->notificationsProvider->getProviderName()
        );
    }
}

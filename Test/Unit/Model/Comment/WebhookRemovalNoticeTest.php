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

namespace Adyen\Payment\Test\Helper\Unit\Model\Comment;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\Comment\WebhookRemovalNotice;
use Adyen\Payment\Model\ResourceModel\Notification\Collection;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Phrase;
use PHPUnit\Framework\MockObject\MockObject;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;

class WebhookRemovalNoticeTest extends AbstractAdyenTestCase
{
    protected ?WebhookRemovalNotice $webhookRemovalNotice;
    protected CollectionFactory|MockObject $notificationCollectionFactoryMock;
    private Config|MockObject $configHelperMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->notificationCollectionFactoryMock =
            $this->createGeneratedMock(CollectionFactory::class, ['create']);
        $this->configHelperMock = $this->createMock(Config::class);

        $this->webhookRemovalNotice = new WebhookRemovalNotice(
            $this->notificationCollectionFactoryMock,
            $this->configHelperMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->webhookRemovalNotice = null;
    }

    /**
     * @return void
     */
    public function testGetCommentText()
    {
        $elementValue = '0';
        $numberOfItems = 10;

        $this->configHelperMock->expects($this->once())
            ->method('getProcessedWebhookRemovalTime')
            ->willReturn(90);

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->expects($this->atLeastOnce())
            ->method('getSize')
            ->willReturn($numberOfItems);

        $this->notificationCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $result = $this->webhookRemovalNotice->getCommentText($elementValue);
        $this->assertInstanceOf(Phrase::class, $result);
        $this->stringContains($result, sprintf("%s processed webhooks", $numberOfItems));
    }
}

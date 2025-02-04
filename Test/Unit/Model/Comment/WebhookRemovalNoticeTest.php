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

use Adyen\Payment\Api\Data\NotificationInterface;
use Adyen\Payment\Cron\Providers\ProcessedWebhooksProvider;
use Adyen\Payment\Model\Comment\WebhookRemovalNotice;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Phrase;
use PHPUnit\Framework\MockObject\MockObject;

class WebhookRemovalNoticeTest extends AbstractAdyenTestCase
{
    protected ?WebhookRemovalNotice $webhookRemovalNotice;
    protected ProcessedWebhooksProvider|MockObject $processedWebhooksProviderMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->processedWebhooksProviderMock = $this->createMock(ProcessedWebhooksProvider::class);
        $this->webhookRemovalNotice = new WebhookRemovalNotice(
            $this->processedWebhooksProviderMock
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

        $providerResult[] = $this->createMock(NotificationInterface::class);
        $this->processedWebhooksProviderMock->expects($this->once())
            ->method('provide')
            ->willReturn($providerResult);

        $result = $this->webhookRemovalNotice->getCommentText($elementValue);
        $this->assertInstanceOf(Phrase::class, $result);
        $this->stringContains($result, sprintf("%s processed webhooks", count($providerResult)));
    }
}

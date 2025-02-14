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

namespace Adyen\Payment\Test\Unit\Block\Checkout;

use Adyen\Payment\Block\Adminhtml\Notification\WebhookRemovalJobNotice;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\MockObject\MockObject;

class WebhookRemovalJobNoticeTest extends AbstractAdyenTestCase
{
    protected ?WebhookRemovalJobNotice $cleanupJobNotice;
    protected Context|MockObject $contextMock;
    protected Config|MockObject $configHelperMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->cleanupJobNotice = new WebhookRemovalJobNotice($this->contextMock, $this->configHelperMock);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cleanupJobNotice = null;
    }

    /**
     * @return void
     */
    public function testIsProcessedWebhookRemovalEnabled()
    {
        $this->configHelperMock->expects($this->once())
            ->method('getIsProcessedWebhookRemovalEnabled')
            ->willReturn(true);

        $this->assertTrue(
            $this->cleanupJobNotice->isProcessedWebhookRemovalEnabled()
        );
    }

    /**
     * @return void
     */
    public function testGetNumberOfDays()
    {
        $days = 90;

        $this->configHelperMock->expects($this->once())
            ->method('getProcessedWebhookRemovalTime')
            ->willReturn($days);

        $this->assertEquals($days, $this->cleanupJobNotice->getNumberOfDays());
    }
}

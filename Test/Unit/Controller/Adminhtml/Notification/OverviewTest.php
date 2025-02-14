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

namespace Adyen\Payment\Test\Unit\Controller\Adminhtml\Notification;

use Adyen\Payment\Controller\Adminhtml\Notifications\Overview;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;

class OverviewTest extends AbstractAdyenTestCase
{
    /**
     * @return void
     */
    public function testExecute()
    {
        $titleMock = $this->createMock(Title::class);
        $titleMock->expects($this->once())
            ->method('prepend')
            ->with(__('Adyen Webhooks Overview'));

        $configMock = $this->createMock(Config::class);
        $configMock->method('getTitle')->willReturn($titleMock);

        $pageMock = $this->createMock(Page::class);
        $pageMock->method('setActiveMenu')
            ->with('Adyen_Payment::notifications_overview')
            ->willReturnSelf();
        $pageMock->method('getConfig')->willReturn($configMock);

        $resultFactoryMock = $this->createMock(ResultFactory::class);
        $resultFactoryMock->expects($this->once())
            ->method('create')
            ->with(ResultFactory::TYPE_PAGE)
            ->willReturn($pageMock);

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getResultFactory')->willReturn($resultFactoryMock);

        $overview = new Overview($contextMock);

        $result = $overview->execute();
        $this->assertInstanceOf(Page::class, $result);
    }
}

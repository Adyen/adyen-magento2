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

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Controller\Adminhtml\Notifications\WebhookReprocess;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class WebhookReprocessTest extends AbstractAdyenTestCase
{
    const ENTITY_ID = 1;
    const REFERER_URL = 'https://localhost/admin/adyen/notifications/overview';

    protected ?WebhookReprocess $webhookReprocessController;
    protected Context|MockObject $contextMock;
    protected ManagerInterface|MockObject $messageManagerMock;
    protected Webhook|MockObject $webhookHelperMock;
    protected AdyenNotificationRepositoryInterface|MockObject $notificationRepositoryMock;
    protected Redirect|MockObject $redirectResultMock;
    protected RequestInterface|MockObject $requestMock;

    protected function setUp(): void
    {
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->webhookHelperMock = $this->createMock(Webhook::class);
        $this->notificationRepositoryMock = $this->createMock(AdyenNotificationRepositoryInterface::class);

        $this->redirectResultMock = $this->createMock(Redirect::class);

        $resultFactoryMock = $this->createMock(ResultFactory::class);
        $resultFactoryMock->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($this->redirectResultMock);

        $redirectMock = $this->createMock(RedirectInterface::class);
        $redirectMock->method('getRefererUrl')->willReturn(self::REFERER_URL);

        $this->requestMock = $this->createMock(RequestInterface::class);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getResultFactory')->willReturn($resultFactoryMock);
        $this->contextMock->method('getRedirect')->willReturn($redirectMock);
        $this->contextMock->method('getRequest')->willReturn($this->requestMock);
        // `messageManager` is overwritten by the parent constructor with the instance from the context.
        $this->contextMock->method('getMessageManager')->willReturn($this->messageManagerMock);

        $this->webhookReprocessController = new WebhookReprocess(
            $this->contextMock,
            $this->messageManagerMock,
            $this->webhookHelperMock,
            $this->notificationRepositoryMock
        );
    }

    protected function tearDown(): void
    {
        $this->webhookReprocessController = null;
    }

    public function testExecuteSuccessfulReprocess()
    {
        $notificationMock = $this->createMock(Notification::class);

        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn((string) self::ENTITY_ID);

        $this->notificationRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(self::ENTITY_ID)
            ->willReturn($notificationMock);

        $this->webhookHelperMock->expects($this->once())
            ->method('processNotification')
            ->with($notificationMock)
            ->willReturn(true);

        $this->redirectResultMock->expects($this->once())
            ->method('setUrl')
            ->with(self::REFERER_URL);

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage')
            ->with(__("Webhook notification reprocessed successfully!"));
        $this->messageManagerMock->expects($this->never())->method('addErrorMessage');

        $result = $this->webhookReprocessController->execute();
        $this->assertInstanceOf(Redirect::class, $result);
    }

    public function testExecuteFailedReprocess()
    {
        $notificationMock = $this->createMock(Notification::class);

        $this->requestMock->method('getParam')->with('entity_id')->willReturn(self::ENTITY_ID);

        $this->notificationRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(self::ENTITY_ID)
            ->willReturn($notificationMock);

        $this->webhookHelperMock->expects($this->once())
            ->method('processNotification')
            ->with($notificationMock)
            ->willReturn(false);

        $this->messageManagerMock->expects($this->never())->method('addSuccessMessage');
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__("Issue occurred while reprocessing the webhook notification!"));

        $result = $this->webhookReprocessController->execute();
        $this->assertInstanceOf(Redirect::class, $result);
    }

    public function testExecuteNonExistingNotification()
    {
        $this->requestMock->method('getParam')->with('entity_id')->willReturn(self::ENTITY_ID);

        $this->notificationRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(self::ENTITY_ID)
            ->willThrowException(new NoSuchEntityException());

        // Assert that reprocessing is not attempted for a non-existing entity.
        $this->webhookHelperMock->expects($this->never())->method('processNotification');

        $this->messageManagerMock->expects($this->never())->method('addSuccessMessage');
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__("The webhook notification could not be found!"));

        $this->redirectResultMock->expects($this->once())
            ->method('setUrl')
            ->with(self::REFERER_URL);

        $result = $this->webhookReprocessController->execute();
        $this->assertInstanceOf(Redirect::class, $result);
    }
}

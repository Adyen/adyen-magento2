<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Notifications;

use Adyen\Payment\Api\Repository\AdyenNotificationRepositoryInterface;
use Adyen\Payment\Helper\Webhook;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;

class WebhookReprocess extends Action
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var Webhook
     */
    private Webhook $webhookHelper;

    /**
     * @var AdyenNotificationRepositoryInterface
     */
    private AdyenNotificationRepositoryInterface $notificationRepository;

    /**
     * Update constructor.
     *
     * @param Context $context
     * @param ManagerInterface $messageManager
     * @param Webhook $webhookHelper
     * @param AdyenNotificationRepositoryInterface $notificationRepository
     */
    public function __construct(
        Context $context,
        ManagerInterface $messageManager,
        Webhook $webhookHelper,
        AdyenNotificationRepositoryInterface $notificationRepository
    ) {
        $this->messageManager = $messageManager;
        $this->webhookHelper = $webhookHelper;
        $this->notificationRepository = $notificationRepository;

        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setUrl($this->_redirect->getRefererUrl());

        try {
            $notification = $this->notificationRepository->getById(
                (int) $this->getRequest()->getParam('entity_id')
            );
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(__("The webhook notification could not be found!"));

            return $redirect;
        }

        if($this->webhookHelper->processNotification($notification)) {
            $this->messageManager->addSuccessMessage(__("Webhook notification reprocessed successfully!"));
        }
        else {
            $this->messageManager->addErrorMessage(__("Issue occurred while reprocessing the webhook notification!"));
        }

        return $redirect;
    }
}

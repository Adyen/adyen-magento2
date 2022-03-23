<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Notifications;

use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Message\ManagerInterface;

class WebhookReprocess extends \Magento\Backend\App\Action
{
    /**
     * @var CollectionFactory
     */
    private $notificationCollectionFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var Webhook
     */
    private $webhookHelper;

    /**
     * Update constructor.
     *
     * @param Context $context
     * @param CollectionFactory $notificationCollectionFactory
     * @param ManagerInterface $messageManager
     * @param Webhook $webhookHelper
     */
    public function __construct(
        Context $context,
        CollectionFactory $notificationCollectionFactory,
        ManagerInterface $messageManager,
        Webhook $webhookHelper
    )
    {
        $this->notificationCollectionFactory = $notificationCollectionFactory;
        $this->messageManager = $messageManager;
        $this->webhookHelper = $webhookHelper;


        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\View\Result\Forward
     */
    public function execute()
    {
        $redirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $redirect->setUrl($this->_redirect->getRefererUrl());

        $notification = $this->notificationCollectionFactory->create();
        $notification = $notification->getItemById($this->getRequest()->getParam('entity_id'));

        if($this->webhookHelper->processNotification($notification)) {
            $this->messageManager->addSuccessMessage(__("Webhook notification reprocessed successfully!"));
        }
        else {
            $this->messageManager->addErrorMessage(__("Issue occured while reprocessing the webhook notification!"));
        }

        return $redirect;
    }
}
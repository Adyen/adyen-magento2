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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Notifications;

class ResetErrorCounter extends \Magento\Backend\App\Action
{

    /**
     * @var \Magento\Ui\Component\MassAction\Filter
     */
    protected $filter;

    /**
     * @var \Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory
     */
    protected $collectionFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Ui\Component\MassAction\Filter $filter,
        \Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Execute mass action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $this->massErrorCounterReset($collection);
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
        }
        $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath($this->_redirect->getRefererUrl());
    }

    /**
     * Run the mass error counter reset
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function massErrorCounterReset(
        \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection $collection
    ) {
        foreach ($collection as $notification) {
            $notification->setErrorCounter(0)->save();
        }
        $this->messageManager->addSuccess(__('A total of %1 notification error counters were reset.',
            $collection->count()));
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Adyen_Payment::reset_error_counter');
    }
}

<?php

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

class Support extends Action
{
    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Adyen_Payment::configuration_support')
            ->getConfig()->getTitle()->prepend(__('Adyen Support Form'));
        return $resultPage;
    }
}

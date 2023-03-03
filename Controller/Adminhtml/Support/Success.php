<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Support;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\Page;

class Success extends Action
{
    /**
     * Load the page defined in corresponding layout XML
     *
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Adyen_Payment::support')
            ->getConfig()->getTitle()->prepend(__('Adyen Support Form'));

        return $resultPage;
    }
}

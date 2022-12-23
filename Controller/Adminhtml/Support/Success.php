<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Support;

use Magento\Backend\App\Action;

class Success extends Action
{
    /**
     * Load the page defined in corresponding layout XML
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Adyen_Payment::configuration_success')
            ->getConfig()->getTitle()->prepend(__('Adyen Support'));

        return $resultPage;
    }
}

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

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Adyen\Payment\Helper\BaseUrlHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class AllowedOriginPrefillAction extends Action
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;
    /**
     * @var BaseUrlHelper
     */
    private $baseUrlHelper;

    public function __construct(Context $context, JsonFactory $jsonFactory, BaseUrlHelper $baseUrlHelper)
    {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->baseUrlHelper = $baseUrlHelper;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        $storeId = $this->getRequest()->getParam('storeId');
        $origin = $this->baseUrlHelper->getStoreBaseUrl($storeId, true);
        $origin = $this->baseUrlHelper->getDomainFromUrl($origin);
        $result->setData(['originUrl' => $origin]);

        return $result;
    }
}

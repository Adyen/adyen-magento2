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

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class WebhookTest extends Action
{
    /**
     * @var ManagementHelper
     */
    private $managementApiHelper;

    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    public function __construct(
        Context $context,
        ManagementHelper $managementApiHelper,
        Data $dataHelper,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->managementApiHelper = $managementApiHelper;
        $this->dataHelper = $dataHelper;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @return ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $merchantAccount = $this->dataHelper->getAdyenMerchantAccount('adyen_cc');
        $response = $this->managementApiHelper->webhookTest($merchantAccount);
        $success = isset($response['data']) && 
            in_array('success', array_column($response['data'], 'status'), true);
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData([
            'messages' => $response,
            'statusCode' => $success ? 'Success' : 'Failed'
        ]);
        return $resultJson;
    }
}

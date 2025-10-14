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

use Adyen\AdyenException;
use Adyen\Model\Management\TestWebhookResponse;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManager;

class WebhookTest extends Action
{
    /**
     * @var ManagementHelper
     */
    private ManagementHelper $managementApiHelper;

    /**
     * @var Config
     */
    protected Config $configHelper;

    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;

    /**
     * @var Context
     */
    protected Context $context;

    /**
     * @var StoreManager
     */
    protected StoreManager $storeManager;

    /**
     * @param Context $context
     * @param ManagementHelper $managementApiHelper
     * @param JsonFactory $resultJsonFactory
     * @param StoreManager $storeManager
     * @param Config $configHelper
     */
    public function __construct(
        Context $context,
        ManagementHelper $managementApiHelper,
        JsonFactory $resultJsonFactory,
        StoreManager $storeManager,
        Config $configHelper
    ) {
        parent::__construct($context);
        $this->managementApiHelper = $managementApiHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->context = $context;
        $this->storeManager = $storeManager;
        $this->configHelper = $configHelper;
    }

    /**
     * @return Json
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function execute(): Json
    {
        $storeId = $this->storeManager->getStore()->getId();

        $merchantAccount = $this->configHelper->getMerchantAccount($storeId);
        $webhookId = $this->configHelper->getWebhookId($storeId);
        $isDemoMode = $this->configHelper->isDemoMode($storeId);
        $environment = $isDemoMode ? 'test' : 'live';
        $apiKey = $this->configHelper->getApiKey($environment, $storeId);

        $client = $this->managementApiHelper->getAdyenApiClient($apiKey, $isDemoMode);
        $service =$this->managementApiHelper->getWebhooksMerchantLevelApi($client);

        $responseObj = $this->managementApiHelper->webhookTest($merchantAccount, $webhookId, $service);

        $success = false;
        $response = null;

        if ($responseObj instanceof TestWebhookResponse) {
            $responseData = $responseObj->getData();
            $responseData = reset($responseData);
            $response = $responseData->toArray();

            $success = $response['status'] === 'success';
        }

        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData([
            'messages' => $response,
            'statusCode' => $success ? 'Success' : 'Failed'
        ]);

        return $resultJson;
    }
}

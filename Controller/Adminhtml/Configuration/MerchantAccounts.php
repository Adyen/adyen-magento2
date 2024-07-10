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

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Adyen\AdyenException;
use Adyen\ConnectionException;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;

class MerchantAccounts extends Action
{
    /**
     * @var ManagementHelper
     */
    protected ManagementHelper $managementHelper;

    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;

    /**
     * @param Context $context
     * @param ManagementHelper $managementHelper
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        ManagementHelper $managementHelper,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->managementHelper = $managementHelper;
    }

    /**
     * @return Json
     * @throws NoSuchEntityException
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        try {
            $apiKey = $this->getRequest()->getParam('apiKey', '');
            $demoMode = (int) $this->getRequest()->getParam('demoMode');
            // Use the stored xapi key if the return value is encrypted chars only or it is empty,
            if (!empty($apiKey) && preg_match('/^\*+$/', (string) $apiKey)) {
                $apiKey = '';
            }

            $client = $this->managementHelper->getAdyenApiClient($apiKey, $demoMode);
            $accountMerchantLevelApi = $this->managementHelper->getAccountMerchantLevelApi($client);
            $myAPICredentialApi = $this->managementHelper->getMyAPICredentialApi($client);
            $response = $this->managementHelper->getMerchantAccountsAndClientKey(
                $accountMerchantLevelApi,
                $myAPICredentialApi
            );

            $resultJson->setData($response);
            return $resultJson;
        } catch (AdyenException $e) {
            $resultJson->setHttpResponseCode(400);
            $resultJson->setData(['error' => $e->getMessage(),]);
        } catch (ConnectionException $e) {
            $resultJson->setHttpResponseCode(500);
            $resultJson->setData(['error' => $e->getMessage(),]);
        }

        return $resultJson;
    }
}

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
use Adyen\ConnectionException;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Backend\App\Action\Context;

class MerchantAccounts extends Action
{
    /**
     * @var ManagementHelper
     */
    protected $managementHelper;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

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
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        try {
            $apiKey = $this->getRequest()->getParam('apiKey', '');
            $demoMode = (int) $this->getRequest()->getParam('demoMode');
            //Use the stored xapi key if the return value is encrypted chars only or it is empty,
            if (!empty($apiKey) && preg_match('/^\*+$/', (string) $apiKey)) {
                $apiKey = '';
            }

            $managementApiService = $this->managementHelper->getManagementApiService($apiKey, $demoMode);
            $response = $this->managementHelper->getMerchantAccountsAndClientKey($managementApiService);

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


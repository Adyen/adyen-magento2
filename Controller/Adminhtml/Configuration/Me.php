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
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Me extends Action
{
    /**
     * @var ManagementHelper
     */
    private $managementHelper;
    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    public function __construct(Context $context, ManagementHelper $managementHelper, JsonFactory $jsonFactory)
    {
        parent::__construct($context);
        $this->managementHelper = $managementHelper;
        $this->jsonFactory = $jsonFactory;
    }

    public function execute()
    {
        $resultJson = $this->jsonFactory->create();
        try {
            $apiKey = $this->getRequest()->getParam('apiKey', '');
            $demoMode = (int) $this->getRequest()->getParam('demoMode');
            //Use the stored api key if the return value is encrypted chars only or if it is empty,
            if (!empty($apiKey) && preg_match('/^\*+$/', $apiKey)) {
                $apiKey = '';
            }

            $response = $this->managementHelper->getClientKey($apiKey, (bool) $demoMode);
            $resultJson->setData($response);
        } catch (AdyenException $e) {
            $resultJson->setHttpResponseCode(400);
            $resultJson->setData(
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

        return $resultJson;
    }
}

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

use Adyen\Payment\Helper\ManagementHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class WebhookTest extends Action
{
    /**
     * @var ManagementHelper
     */
    private $managementApiHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    public function __construct(
        Context $context,
        ManagementHelper $managementApiHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->managementApiHelper = $managementApiHelper;
        $this->adyenHelper =$adyenHelper;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|mixed|string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount('adyen_cc');
        $response = $this->managementApiHelper->webhookTest($merchantAccount);
        $resultJson = $this->resultJsonFactory->create();
        if (isset($response['data']) && in_array(
                'success',
                array_column(
                    $response['data'],
                    'status'
                ),
                true
            )) {
            $resultJson->setData(
                [
                    'messages' => $response,
                    'statusCode' => 'Success'
                ]
            );
        } else {
            $resultJson->setData(
                [
                    'messages' => $response,
                    'statusCode' => 'Failed'
                ]
            );
        }
        return $resultJson;
    }
}

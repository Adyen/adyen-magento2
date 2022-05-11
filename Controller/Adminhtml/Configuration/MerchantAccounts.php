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
use Adyen\Payment\Helper\BaseUrlHelper;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Backend\App\Action;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Backend\App\Action\Context;

class MerchantAccounts extends Action
{

    const TEST_MODE = 'test';
    const LIVE_MODE = 'production';
    /**
     * @var ManagementHelper
     */
    protected $managementHelper;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /** @var Http */
    protected $request;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var BaseUrlHelper
     */
    private $baseUrlHelper;

    public function __construct(
        Context $context,
        ManagementHelper $managementHelper,
        JsonFactory $resultJsonFactory,
        Http $request,
        BaseUrlHelper $baseUrlHelper,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->managementHelper = $managementHelper;
        $this->request = $request;
        $this->adyenHelper = $adyenHelper;
        $this->baseUrlHelper = $baseUrlHelper;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        try {
            $xapikey = $this->getRequest()->getParam('xapikey', '');
            $demoMode = $this->getRequest()->getParam('demoMode', '');
            //Use the stored xapi key if the return value is encrypted chars only or it is empty,
            if (!empty($xapikey) && preg_match('/^\*+$/', $xapikey)) {
                $xapikey = '';
            }

            $response = $this->managementHelper->getMerchantAccountAndClientKey($xapikey);
            $currentMerchantAccount = $this->adyenHelper->getAdyenMerchantAccount('adyen_cc');

            $storeId = $this->getRequest()->getParam('storeId');
            $origin = $this->baseUrlHelper->getStoreBaseUrl($storeId, true);
            $origin = $this->baseUrlHelper->getDomainFromUrl($origin);

            $resultJson = $this->resultJsonFactory->create();
            $resultJson->setData(
                [
                    'messages' => $response,
                    'mode' => $this->getDemoMode($demoMode),
                    'currentMerchantAccount' => $currentMerchantAccount,
                    'originUrl' => $origin
                ]
            );
            return $resultJson;
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

    private function getDemoMode($mode): string
    {
        if ($mode == 0) {
            return self::LIVE_MODE;
        }
        return self::TEST_MODE;
    }
}


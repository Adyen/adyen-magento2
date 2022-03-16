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
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Context;

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

    /** @var \Magento\Framework\App\Request\Http */
    protected $request;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    public function __construct(
        Context $context,
        ManagementHelper $managementHelper,
        JsonFactory $resultJsonFactory,
        \Magento\Framework\App\Request\Http $request,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->managementHelper = $managementHelper;
        $this->request = $request;
        $this->_adyenHelper = $adyenHelper;
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
            if (str_contains($xapikey, '*')) {
                $xapikey = '';
            }
            $response = $this->managementHelper->getMerchantAccountWithClientkey($xapikey);
            $currentMerchantAccount = $this->_adyenHelper->getAdyenMerchantAccount('adyen_cc');
            $resultJson = $this->resultJsonFactory->create();
            $resultJson->setData(
                [
                    'messages' => $response,
                    'mode' => $this->getDemoMode($demoMode),
                    'currentMerchantAccount' => $currentMerchantAccount
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


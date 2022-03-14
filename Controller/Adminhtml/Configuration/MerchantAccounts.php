<?php

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Adyen\AdyenException;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class MerchantAccounts extends \Magento\Backend\App\Action
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
        try {
            $request = $this->request;
            $key = $this->request->getParam('xapikey');
            $currentMerchantAccount = $this->_adyenHelper->getAdyenMerchantAccount('adyen_cc');
            $response = $this->managementHelper->getMerchantAccountWithClientkey();
            $resultJson = $this->resultJsonFactory->create();
            $resultJson->setData(['messages' => $response, 'mode' => self::TEST_MODE,
                                 'currentMerchantAccount'=>$currentMerchantAccount]);

            return $resultJson;
        } catch (AdyenException $e) {
        }
    }
}
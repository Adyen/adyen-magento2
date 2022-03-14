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
        try {
            $response = $this->managementHelper->getMerchantAccountWithClientkey();
            $resultJson = $this->resultJsonFactory->create();
            $resultJson->setData(['messages' => $response, 'mode' => self::TEST_MODE]);

            return $resultJson;
        } catch (AdyenException $e) {
        }
    }
}
<?php

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Adyen\AdyenException;
use Magento\Framework\App\Action\Context;

class MerchantAccounts extends \Magento\Backend\App\Action
{

    /**
     * @var \Adyen\Payment\Helper\ManagementApi
     */
    protected $managementApi;

    public function __construct(
        Context $context,
        \Adyen\Payment\Helper\ManagementApi $managementApi
    ) {
        parent::__construct($context);

        $this->managementApi = $managementApi;
    }

    /**
     * @throws \Adyen\AdyenException
     */
    public function execute()
    {
        try {
            $test = $this->managementApi->createMerchantAccountResource()->list();
        } catch (AdyenException $e) {
        }
    }
}
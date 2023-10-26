<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

class AdyenPmConfigProvider implements ConfigProviderInterface
{
    protected Config $configHelper;
    protected RequestInterface $request;
    protected Session $session;
    protected StoreManagerInterface $storeManager;

    public function __construct(
        RequestInterface $request,
        Session $session,
        StoreManagerInterface $storeManager,
        Config $configHelper
    ) {
        $this->request = $request;
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->configHelper = $configHelper;
    }

    public function getConfig(): array
    {
        $storeId = $this->storeManager->getStore()->getId();

        return [
            'payment' => [
                'adyenPm' => [
                    'ratePayId' => $this->configHelper->getRatePayId($storeId),
                    'deviceIdentToken' =>  hash("sha256", $this->session->getQuoteId() . date('c'))
                ]
            ]
        ];
    }

    protected function getRequest(): RequestInterface
    {
        return $this->request;
    }
}

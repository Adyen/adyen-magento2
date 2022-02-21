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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;


use Magento\Backend\Helper\Data as BackendDataHelper;
use Magento\Framework\App\State;
use Magento\Framework\UrlInterface;

class ReturnUrlHelper
{
    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var State
     */
    private $state;

    /**
     * @var BackendDataHelper
     */
    private $backendHelper;

    /**
     * @var Config
     */
    private $config;

    public function __construct(UrlInterface $url, State $state, BackendDataHelper $backendHelper, Config $config)
    {
        $this->url = $url;
        $this->state = $state;
        $this->backendHelper = $backendHelper;
        $this->config = $config;
    }

    /**
     * @param null|int|string $storeId
     * @return string
     */
    public function getStoreReturnUrl($storeId)
    {
        if ($paymentReturnUrl = $this->config->getPWAReturnUrl($storeId)) {
            return rtrim($paymentReturnUrl, '/');
        }
        
        if ('adminhtml' === $this->state->getAreaCode()) {
            return rtrim($this->backendHelper->getHomePageUrl(), '/') . '/adyen/process/result';
        }
        
        return rtrim($this->url->getBaseUrl(), '/') . '/adyen/process/result';
    }
}
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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

/**
 * Class ManagementApi
 * @package Adyen\Payment\Helper
 */

use Adyen\Service\Management;
use Magento\Store\Model\StoreManager;

class ManagementHelper
{
    /**
     * @var Management
     */
    protected $management;

    /**
     * ManagementHelper constructor.
     * @param StoreManager $storeManager
     * @param Data $adyenHelper
     * @throws \Adyen\AdyenException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function __construct(StoreManager $storeManager, Data $adyenHelper)
    {
         $storeId = $storeManager->getStore()->getId();
         $client = $adyenHelper->initializeAdyenClient($storeId);
         $this->management = new \Adyen\Service\Management($client);
    }

    /**
     * @return array
     * @throws \Adyen\AdyenException
     */
    public function getMerchantAccountWithClientkey()
    {
        $merchantAccount = [];
        $response = $this->management->me->retrieve();
        $merchantAccount['clientKey'] = $response['clientKey'];
        $merchantAccount['associatedMerchantAccounts']= $response['associatedMerchantAccounts'];
        return $merchantAccount;
    }
}
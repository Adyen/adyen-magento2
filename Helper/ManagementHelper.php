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
     * @var Data
     */
    private $adyenHelper;
    /**
     * @var StoreManager
     */
    private $storeManager;

    /**
     * ManagementHelper constructor.
     * @param StoreManager $storeManager
     * @param Data $adyenHelper
     * @throws \Adyen\AdyenException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function __construct(StoreManager $storeManager, Data $adyenHelper)
    {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * @param $xapikey
     * @return array
     * @throws \Adyen\AdyenException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getMerchantAccountWithClientkey(string $xapikey): array
    {
        $storeId = $this->storeManager->getStore()->getId();
        $client = $this->adyenHelper->initializeAdyenClient($storeId, $xapikey);
        $management = $this->adyenHelper->createManagementService($client);
        $response = $management->me->retrieve();
        return [
            'clientKey' => $response['clientKey'],
            'associatedMerchantAccounts' => $response['associatedMerchantAccounts'],
        ];
    }
}
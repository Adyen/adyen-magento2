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

class ManagementApi
{
    /**
     * @var ManagementApi
     */
    protected $managementapi;

    /**
     * ManagementApi constructor.
     * @param $management
     * @param \Adyen\
     * @throws \Adyen\AdyenException
     */
    public function __construct($managementapi)
    {
        $this->managementapi = $managementapi;
    }

    /**
     * @param $client
     * @return \Adyen\Service\ResourceModel\Management\MerchantAccount
     */
    public function createMerchantAccountResource()
    {
        return $this->management->merchantAccount;
    }
}
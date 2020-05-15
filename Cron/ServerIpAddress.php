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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron;

use Adyen\Payment\Helper\IpAddress;

class ServerIpAddress
{

    /**
     * @var IpAddress $ipAddressHelper
     */
    protected $ipAddressHelper;

    public function __construct(
        IpAddress $ipAddressHelper
    ) {
        $this->ipAddressHelper = $ipAddressHelper;
    }

    public function execute()
    {
        //Check if there are already verified IP addresses in cache and refresh when empty
        if (empty($this->ipAddressHelper->getIpAddressesFromCache())) {
            $this->ipAddressHelper->updateCachedIpAddresses();
        }
    }
}

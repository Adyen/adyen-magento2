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
use Adyen\Payment\Logger\AdyenLogger;

class ServerIpAddress
{

    /**
     * @var IpAddress $ipAddressHelper
     */
    protected $ipAddressHelper;

    /**
     * @var AdyenLogger $adyenLogger
     */
    protected $adyenLogger;

    /**
     * ServerIpAddress constructor.
     * @param IpAddress $ipAddressHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        IpAddress $ipAddressHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->ipAddressHelper = $ipAddressHelper;
        $this->adyenLogger = $adyenLogger;
    }

    public function execute()
    {
        //Check if there are already verified IP addresses in cache and refresh when empty
        if (empty($this->ipAddressHelper->getIpAddressesFromCache())) {
            $this->adyenLogger->addAdyenNotificationCronjob(
                'There are no verified Adyen IP addresses in cache. Updating IP records.'
            );
            $this->ipAddressHelper->updateCachedIpAddresses();
        }
    }
}

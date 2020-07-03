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

namespace Adyen\Payment\Model;

class ApplicationInfo
{
    const APPLICATION_INFO = 'applicationInfo';
    const MERCHANT_APPLICATION = 'merchantApplication';
    const EXTERNAL_PLATFORM = 'externalPlatform';
    const NAME = 'name';
    const VERSION = 'version';

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * ApplicationInfo constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * Add this application into request as we are missing the client function in the current library version
     * when we upgrade to latest version we can use the client method instead
     *
     * @param $request
     * @return mixed
     */
    public function addMerchantApplicationIntoRequest($request)
    {
        // add applicationInfo into request
        $request[self::APPLICATION_INFO][self::MERCHANT_APPLICATION][self::NAME] = $this->adyenHelper->getModuleName();
        $request[self::APPLICATION_INFO][self::MERCHANT_APPLICATION][self::VERSION] =
            $this->adyenHelper->getModuleVersion();
        return $request;
    }
}

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
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;

class AdyenPayByLinkConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_pay_by_link';
    const MIN_EXPIRY_DAYS = 1;
    const MAX_EXPIRY_DAYS = 70;
    const DAYS_TO_EXPIRE_CONFIG_PATH = 'payment/adyen_pay_by_link/days_to_expire';
    const DATE_FORMAT = 'd-m-Y';
    const EXPIRES_AT_KEY = 'payByLinkExpiresAt';
    const URL_KEY = 'payByLinkUrl';
    const ID_KEY = 'payByLinkId';

    /**
     * Request object
     *
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @param RequestInterface $request
     */
    public function __construct(
        RequestInterface $request
    ) {
        $this->_request = $request;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        // set to active
        return [
            'payment' => [
                self::CODE => [
                    'isActive' => true
                ]
            ]
        ];
    }

    /**
     * Retrieve request object
     *
     * @return RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }
}

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
 * Adyen Payment Module
 *
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Directory\Helper\Data;


class AdyenPosCloudConfigProvider implements ConfigProviderInterface
{

    const CODE = 'adyen_pos_cloud';

    protected $_adyenLogger;
    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * AdyenHppConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger

    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->_request = $request;
        $this->_urlBuilder = $urlBuilder;
        $this->_adyenLogger = $adyenLogger;
    }

    /**
     * Set configuration for POS Cloud Api payment method
     *
     * @return array
     */
    public function getConfig()
    {
        // set to active
        $config = [
            'payment' => [
                self::CODE => [
                    'isActive' => true,
                    'redirectUrl' => $this->_urlBuilder->getUrl(
                        '/checkout/onepage/success/', ['_secure' => $this->_getRequest()->isSecure()])
                ]
            ]
        ];

        return $config;
    }

    /**
     * Retrieve request object
     *
     * @return \Magento\Framework\App\RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }
}
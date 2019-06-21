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

class AdyenPosCloudConfigProvider implements ConfigProviderInterface
{

    const CODE = 'adyen_pos_cloud';
    
    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Adyen\Payment\Helper\PaymentMethods
     */
    protected $paymentMethodsHelper;

    /**
     * AdyenHppConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Adyen\Payment\Helper\PaymentMethods $paymentMethodsHelper
    ) {
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
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
                    'redirectUrl' => $this->urlBuilder->getUrl(
                        '/checkout/onepage/success/',
                        ['_secure' => $this->getRequest()->isSecure()]
                    )
                ]
            ]
        ];

        $config['payment']['adyenPos']['connectedTerminals'] = $this->getConnectedTerminals();

        return $config;
    }

    /**
     * Retrieve request object
     *
     * @return \Magento\Framework\App\RequestInterface
     */
    protected function getRequest()
    {
        return $this->request;
    }

    /**
     * @return array|mixed
     * @throws \Adyen\AdyenException
     */
    protected function getConnectedTerminals()
    {
        $connectedTerminals = $this->paymentMethodsHelper->getConnectedTerminals();

        if (!empty($connectedTerminals['uniqueTerminalIds'])) {
            return $connectedTerminals['uniqueTerminalIds'];
        }

        return [];
    }
}

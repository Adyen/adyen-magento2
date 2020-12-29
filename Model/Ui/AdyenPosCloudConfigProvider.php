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
     * @var \Adyen\Payment\Helper\ConnectedTerminals
     */
    protected $connectedTerminalsHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * AdyenHppConfigProvider constructor.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Adyen\Payment\Helper\ConnectedTerminals $connectedTerminalsHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Adyen\Payment\Helper\ConnectedTerminals $connectedTerminalsHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Serialize\SerializerInterface $serializer
    ) {
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->connectedTerminalsHelper = $connectedTerminalsHelper;
        $this->adyenHelper = $adyenHelper;
        $this->serializer = $serializer;
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

        if ($this->adyenHelper->getAdyenPosCloudConfigDataFlag("active")) {
            $config['payment']['adyenPos']['connectedTerminals'] = $this->getConnectedTerminals();
        }

        // has installments by default false
        $config['payment']['adyenPos']['hasInstallments'] = false;

        // get Installments
        $installmentsEnabled = $this->adyenHelper->getAdyenPosCloudConfigData('enable_installments');
        $installments = $this->adyenHelper->getAdyenPosCloudConfigData('installments');

        if ($installmentsEnabled && $installments) {
            $config['payment']['adyenPos']['installments'] = $this->serializer->unserialize($installments);
            $config['payment']['adyenPos']['hasInstallments'] = true;
        } else {
            $config['payment']['adyenPos']['installments'] = [];
        }

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
        $connectedTerminals = $this->connectedTerminalsHelper->getConnectedTerminals();

        if (!empty($connectedTerminals['uniqueTerminalIds'])) {
            return $connectedTerminals['uniqueTerminalIds'];
        }

        return [];
    }
}

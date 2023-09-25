<?php
/**
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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ConnectedTerminals;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;

class AdyenPosCloudConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_pos_cloud';

    /** @var RequestInterface  */
    protected $request;

    /** @var UrlInterface  */
    protected $urlBuilder;

    /** @var ConnectedTerminals  */
    protected $connectedTerminalsHelper;

    /** @var Config */
    protected $configHelper;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        RequestInterface $request,
        UrlInterface $urlBuilder,
        ConnectedTerminals $connectedTerminalsHelper,
        SerializerInterface $serializer,
        Config $configHelper
    ) {
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->connectedTerminalsHelper = $connectedTerminalsHelper;
        $this->configHelper = $configHelper;
        $this->serializer = $serializer;
    }

    /**
     * Set configuration for POS Terminal payment method
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
                    'successPage' => $this->urlBuilder->getUrl(
                        '/checkout/onepage/success/',
                        ['_secure' => $this->getRequest()->isSecure()]
                    )
                ]
            ]
        ];

        if ($this->configHelper->getAdyenPosCloudConfigData("active", null, true)) {
            $config['payment']['adyenPos']['fundingSourceOptions'] = $this->getFundingSourceOptions();
        }

        // has installments by default false
        $config['payment']['adyenPos']['hasInstallments'] = false;

        // get Installments
        $installmentsEnabled = $this->configHelper->getAdyenPosCloudConfigData('enable_installments');
        $installments = $this->configHelper->getAdyenPosCloudConfigData('installments');

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
     * @return RequestInterface
     */
    protected function getRequest()
    {
        return $this->request;
    }

    /**
     * @return string[]
     */
    protected function getFundingSourceOptions(): array
    {
        return [
            'credit' => 'Credit Card',
            'debit' => 'Debit Card'
        ];
    }
}

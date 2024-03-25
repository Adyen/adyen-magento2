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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManager;

class AdyenPosCloudConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_pos_cloud';

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlBuilder;

    /**
     * @var ConnectedTerminals
     */
    protected ConnectedTerminals $connectedTerminalsHelper;

    /**
     * @var Config
     */
    protected Config $configHelper;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var StoreManager
     */
    private StoreManager $storeManager;

    /**
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @param ConnectedTerminals $connectedTerminalsHelper
     * @param SerializerInterface $serializer
     * @param Config $configHelper
     * @param StoreManager $storeManager
     */
    public function __construct(
        RequestInterface $request,
        UrlInterface $urlBuilder,
        ConnectedTerminals $connectedTerminalsHelper,
        SerializerInterface $serializer,
        Config $configHelper,
        StoreManager $storeManager
    ) {
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->connectedTerminalsHelper = $connectedTerminalsHelper;
        $this->configHelper = $configHelper;
        $this->serializer = $serializer;
        $this->storeManager = $storeManager;
    }

    /**
     * Set configuration for POS Terminal payment method
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfig(): array
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

        $storeId = $this->storeManager->getStore()->getId();

        $config['payment']['adyenPos']['paymentAction'] = $this->configHelper->getAdyenPosCloudPaymentAction($storeId);

        if ($this->configHelper->getAdyenPosCloudConfigData("active", $storeId, true)) {
            $config['payment']['adyenPos']['fundingSourceOptions'] = $this->getFundingSourceOptions();
        }

        // has installments by default false
        $config['payment']['adyenPos']['hasInstallments'] = false;

        // get Installments
        $installmentsEnabled = $this->configHelper->getAdyenPosCloudConfigData('enable_installments', $storeId);
        $installments = $this->configHelper->getAdyenPosCloudConfigData('installments', $storeId);

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
    protected function getRequest(): RequestInterface
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

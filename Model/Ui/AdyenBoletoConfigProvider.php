<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

class AdyenBoletoConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_boleto';

    protected PaymentHelper $paymentHelper;
    protected Data $_adyenHelper;
    protected Config $_configHelper;
    protected UrlInterface $urlBuilder;
    protected RequestInterface $request;

    public function __construct(
        PaymentHelper    $paymentHelper,
        Data             $adyenHelper,
        Config           $configHelper,
        UrlInterface     $urlBuilder,
        RequestInterface $request
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->_configHelper = $configHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
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
                    'isActive' => true,
                    'successPage' => $this->urlBuilder->getUrl(
                        'checkout/onepage/success/',
                        ['_secure' => $this->_getRequest()->isSecure()]
                    )
                ],
                'adyenBoleto' => [
                    'boletoTypes' => $this->getBoletoAvailableTypes()
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    protected function getBoletoAvailableTypes()
    {
        $types = [];
        $boletoTypes = $this->_adyenHelper->getBoletoTypes();
        $availableTypes = $this->_configHelper->getAdyenBoletoConfigData('boletotypes');
        if ($availableTypes) {
            $availableTypes = explode(',', (string) $availableTypes);
            foreach ($boletoTypes as $boletoType) {
                if (in_array($boletoType['value'], $availableTypes)) {
                    $types[] = $boletoType;
                }
            }
        }
        return $types;
    }

    /**
     * Retrieve request object
     *
     * @return RequestInterface
     */
    protected function _getRequest()
    {
        return $this->request;
    }
}

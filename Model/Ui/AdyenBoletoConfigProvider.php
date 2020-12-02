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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

class AdyenBoletoConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_boleto';

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * AdyenBoletoConfigProvider constructor.
     *
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->_urlBuilder = $urlBuilder;
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
                    'isActive' => true,
                    'redirectUrl' => $this->_urlBuilder->getUrl(
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
        $availableTypes = $this->_adyenHelper->getAdyenBoletoConfigData('boletotypes');
        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);
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
     * @return \Magento\Framework\App\RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }
}

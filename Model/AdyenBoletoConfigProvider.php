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

namespace Adyen\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

class AdyenBoletoConfigProvider implements ConfigProviderInterface
{

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;
    
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var string[]
     */
    protected $_methodCodes = [
        \Adyen\Payment\Model\Method\Boleto::METHOD_CODE
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $_methods = [];


    /**
     * AdyenBoletoConfigProvider constructor.
     *
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;

        foreach ($this->_methodCodes as $code) {
            $this->_methods[$code] = $this->_paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $config = [];

        foreach ($this->_methodCodes as $code) {
            if ($this->_methods[$code]->isAvailable()) {
                $config = [
                    'payment' => [
                        'adyenBoleto' => [
                            'boletoTypes' => $this->_adyenHelper->getBoletoTypes()
                        ]
                    ]
                ];
            }
        }

        return $config;
    }
}
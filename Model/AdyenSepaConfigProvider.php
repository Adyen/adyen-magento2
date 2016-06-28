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
use Magento\Payment\Helper\Data as PaymentHelper;

class AdyenSepaConfigProvider implements ConfigProviderInterface
{

    /**
     * @var string[]
     */
    protected $_methodCodes = [
        'adyen_sepa'
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $_methods = [];

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * AdyenSepaConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        PaymentHelper $paymentHelper,
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
        $config = [
            'payment' => [
                'adyenSepa' => [
                    'countries' => $this->_adyenHelper->getSepaCountries()
                ]
            ]
        ];
        return $config;
    }
}
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
use Magento\Directory\Helper\Data;

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
     * @var \Magento\Directory\Model\Config\Source\Country
     */
    protected $_country;

    /**
     * AdyenSepaConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param \Magento\Directory\Model\Config\Source\Country $country
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        \Magento\Directory\Model\Config\Source\Country $country
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_country = $country;

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
                    'countries' => $this->getCountries()
                ]
            ]
        ];

        return $config;
    }

    /**
     * @return array
     */
    public function getCountries()
    {
        $sepaCountriesAllowed = [
            "AT", "BE", "BG", "CH", "CY", "CZ", "DE", "DK", "EE", "ES", "FI", "FR", "GB", "GF", "GI", "GP", "GR", "HR",
            "HU", "IE", "IS", "IT", "LI", "LT", "LU", "LV", "MC", "MQ", "MT", "NL", "NO", "PL", "PT", "RE", "RO", "SE",
            "SI", "SK"
        ];

        $countryList = $this->_country->toOptionArray();
        $sepaCountries = [];

        foreach ($countryList as $key => $country) {
            $value = $country['value'];
            if (in_array($value, $sepaCountriesAllowed)) {
                $sepaCountries[$value] = $country['label'];
            }
        }
        return $sepaCountries;
    }
}
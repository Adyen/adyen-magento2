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

class AdyenGenericConfigProvider implements ConfigProviderInterface
{

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
     * @var string[]
     */
    protected $_methodCodes = [
        \Adyen\Payment\Model\Method\Pos::METHOD_CODE,
    ];

    /**
     * AdyenGenericConfigProvider constructor.
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
     * Define foreach payment methods the RedirectUrl
     *
     * @return array
     */
    public function getConfig()
    {
        $config = [
            'payment' => []
        ];

        foreach ($this->_methodCodes as $code) {
            if ($this->_methods[$code]->isAvailable()) {

                $config['payment'][$code] = [
                    'redirectUrl' => $this->getMethodRedirectUrl($code)
                ];
            }
        }

        // show logos turned on by default
        if ($this->_adyenHelper->showLogos()) {
            $config['payment']['adyen']['showLogo'] = true;
        } else {
            $config['payment']['adyen']['showLogo'] = false;
        }
        return $config;
    }

    /**
     * Return redirect URL for method
     *
     * @param string $code
     * @return mixed
     */
    protected function getMethodRedirectUrl($code)
    {
        return $this->_methods[$code]->getCheckoutRedirectUrl();
    }
}
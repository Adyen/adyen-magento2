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

class AdyenHppConfigProvider implements ConfigProviderInterface
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
        'adyen_hpp'
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * AdyenHppConfigProvider constructor.
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
            $this->methods[$code] = $this->_paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * Set configuration for AdyenHPP payemnt method
     *
     * @return array
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                'adyenHpp' => [
                ]
            ]
        ];

        foreach ($this->_methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $paymentMethodSelectionOnAdyen =
                    $this->_adyenHelper->getAdyenHppConfigDataFlag('payment_selection_on_adyen');
                $config['payment'] ['adyenHpp']['isPaymentMethodSelectionOnAdyen'] = $paymentMethodSelectionOnAdyen;
            }
        }
        return $config;
    }
}
<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Adyen\Payment\Model;

use Magento\Payment\Model\CcGenericConfigProvider;
use Magento\Payment\Helper\Data as PaymentHelper;

class AdyenCcConfigProvider extends CcGenericConfigProvider
{

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var string[]
     */
    protected $methodCodes = [
        \Adyen\Payment\Model\Method\Cc::METHOD_CODE
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    protected $adyenHelper;

    /**
     * @param CcConfig $ccConfig
     * @param PaymentHelper $paymentHelper
     * @param array $methodCodes
     */
    public function __construct(
        \Magento\Payment\Model\CcConfig $ccConfig,
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        parent::__construct($ccConfig, $paymentHelper, $this->methodCodes);
        $this->adyenHelper = $adyenHelper;
    }

    public function getConfig()
    {
        $config = parent::getConfig();

        $demoMode = $this->adyenHelper->getAdyenAbstractConfigDataFlag('demo_mode');

        if($demoMode) {
            $cseKey = $this->adyenHelper->getAdyenCcConfigData('cse_publickey_test');
        } else {
            $cseKey = $this->adyenHelper->getAdyenCcConfigData('cse_publickey_live');
        }

        $cseEnabled = $this->adyenHelper->getAdyenCcConfigDataFlag('cse_enabled');

        $config['payment'] ['adyenCc']['cseKey'] = $cseKey;
        $config['payment'] ['adyenCc']['cseEnabled'] = $cseEnabled;
        $config['payment'] ['adyenCc']['cseEnabled'] = $cseEnabled;
        $config['payment']['adyenCc']['generationTime'] = date("c");

        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment']['adyenCc']['redirectUrl'][$code] = $this->getMethodRedirectUrl($code);
            }
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
        return $this->methods[$code]->getCheckoutRedirectUrl();
    }
}
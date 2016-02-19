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

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var AdyenGenericConfig
     */
    protected $_genericConfig;

    /**
     * @param \Magento\Payment\Model\CcConfig $ccConfig
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Payment\Model\CcConfig $ccConfig,
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\AdyenGenericConfig $genericConfig
    ) {
        parent::__construct($ccConfig, $paymentHelper, $this->methodCodes);
        $this->adyenHelper = $adyenHelper;
        $this->_genericConfig = $genericConfig;
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

        $recurringType = $this->adyenHelper->getAdyenAbstractConfigData('recurring_type');
        $canCreateBillingAgreement = false;
        if($recurringType == "ONECLICK" || $recurringType == "ONECLICK,RECURRING") {
            $canCreateBillingAgreement = true;
        }


        $config['payment'] ['adyenCc']['cseKey'] = $cseKey;
        $config['payment'] ['adyenCc']['cseEnabled'] = $cseEnabled;
        $config['payment'] ['adyenCc']['cseEnabled'] = $cseEnabled;
        $config['payment']['adyenCc']['generationTime'] = date("c");
        $config['payment']['adyenCc']['canCreateBillingAgreement'] = $canCreateBillingAgreement;

        // show logos turned on by default
        if($this->_genericConfig->showLogos()) {
            $config['payment']['adyenCc']['creditCardPaymentMethodIcon'] = $this->_getCreditCardPaymentMethodIcon();
        }

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

    protected function _getCreditCardPaymentMethodIcon()
    {
        $asset = $this->_genericConfig->createAsset('Adyen_Payment::images/logos/img_trans.gif');

        $placeholder = $this->_genericConfig->findRelativeSourceFilePath($asset);

        $icon = null;
        if ($placeholder) {
            list($width, $height) = getimagesize($asset->getSourceFile());
            $icon = [
                'url' => $asset->getUrl(),
                'width' => $width,
                'height' => $height
            ];
        }

        return $icon;
    }
}
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
use Magento\Framework\View\Asset\Source as Source;

class AdyenCcConfigProvider extends CcGenericConfigProvider
{

    /**
     * @var string[]
     */
    protected $_methodCodes = [
        \Adyen\Payment\Model\Method\Cc::METHOD_CODE
    ];

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var AdyenGenericConfig
     */
    protected $_genericConfig;

    /**
     * @var Source
     */
    protected $_assetSource;

    /**
     * AdyenCcConfigProvider constructor.
     *
     * @param \Magento\Payment\Model\CcConfig $ccConfig
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param AdyenGenericConfig $genericConfig
     * @param Source $assetSource
     */
    public function __construct(
        \Magento\Payment\Model\CcConfig $ccConfig,
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\AdyenGenericConfig $genericConfig,
        Source $assetSource
    ) {
        parent::__construct($ccConfig, $paymentHelper, $this->_methodCodes);
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->_genericConfig = $genericConfig;
        $this->_assetSource = $assetSource;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        foreach ($this->_methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {

                $demoMode = $this->_adyenHelper->getAdyenAbstractConfigDataFlag('demo_mode');

                if ($demoMode) {
                    $cseKey = $this->_adyenHelper->getAdyenCcConfigData('cse_publickey_test');
                } else {
                    $cseKey = $this->_adyenHelper->getAdyenCcConfigData('cse_publickey_live');
                }

                $cseEnabled = $this->_adyenHelper->getAdyenCcConfigDataFlag('cse_enabled');

                $recurringType = $this->_adyenHelper->getAdyenAbstractConfigData('recurring_type');
                $canCreateBillingAgreement = false;
                if ($recurringType == "ONECLICK" || $recurringType == "ONECLICK,RECURRING") {
                    $canCreateBillingAgreement = true;
                }

                $config['payment'] ['adyenCc']['cseKey'] = $cseKey;
                $config['payment'] ['adyenCc']['cseEnabled'] = $cseEnabled;
                $config['payment'] ['adyenCc']['cseEnabled'] = $cseEnabled;
                $config['payment']['adyenCc']['generationTime'] = date("c");
                $config['payment']['adyenCc']['canCreateBillingAgreement'] = $canCreateBillingAgreement;

                // show logos turned on by default
                if ($this->_genericConfig->showLogos()) {
                    $config['payment']['adyenCc']['creditCardPaymentMethodIcon'] = $this->_getCreditCardPaymentMethodIcon();
                }

                $config['payment']['adyenCc']['icons'] = $this->getIcons();

                // has installments by default false
                $config['payment']['adyenCc']['hasInstallments'] = false;
                
                // get Installments
                $installments = $this->_adyenHelper->getAdyenCcConfigData('installments');

                if ($installments) {
                    $config['payment']['adyenCc']['installments'] = unserialize($installments);
                    $config['payment']['adyenCc']['hasInstallments'] = true;
                } else {
                    $config['payment']['adyenCc']['installments'] = [];
                }
            }
        }

        return $config;
    }

    /**
     * @return array|null
     */
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

    /**
     * Retrieve availables credit card types
     *
     * @param string $methodCode
     * @return array
     */
    protected function getCcAvailableTypes($methodCode)
    {
        $types = [];
        $ccTypes = $this->_adyenHelper->getAdyenCcTypes();
        $availableTypes = $this->methods[$methodCode]->getConfigData('cctypes');
        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);
            foreach (array_keys($ccTypes) as $code) {
                if (in_array($code, $availableTypes)) {
                    $types[$code] = $ccTypes[$code]['name'];
                }
            }
        }

        return $types;
    }

    /**
     * Get icons for available payment methods
     *
     * @return array
     */
    protected function getIcons()
    {
        $icons = [];
        $types = $this->_adyenHelper->getAdyenCcTypes();
        foreach (array_keys($types) as $code) {
            if (!array_key_exists($code, $icons)) {
                $asset = $this->ccConfig->createAsset('Magento_Payment::images/cc/' . strtolower($code) . '.png');
                $placeholder = $this->_assetSource->findRelativeSourceFilePath($asset);
                if ($placeholder) {
                    list($width, $height) = getimagesize($asset->getSourceFile());
                    $icons[$code] = [
                        'url' => $asset->getUrl(),
                        'width' => $width,
                        'height' => $height
                    ];
                }
            }
        }
        return $icons;
    }
}
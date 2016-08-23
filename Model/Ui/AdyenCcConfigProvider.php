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
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\View\Asset\Source as Source;
use \Magento\Payment\Gateway\Config\Config as Config;

class AdyenCcConfigProvider implements ConfigProviderInterface
{

    const CODE = 'adyen_cc';

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var Source
     */
    protected $_assetSource;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Payment\Model\CcConfig
     */
    private $ccConfig;
    

    /**
     * AdyenCcConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param Source $assetSource
     * @param \Magento\Payment\Model\CcConfig $ccConfig
     * @param Config $config
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        Source $assetSource,
        \Magento\Payment\Model\CcConfig $ccConfig
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->_request = $request;
        $this->_urlBuilder = $urlBuilder;
        $this->_assetSource = $assetSource;
        $this->ccConfig = $ccConfig;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        // set to active
        $config = [
            'payment' => [
                self::CODE => [
                    'isActive' => true,
                    'redirectUrl' => $this->_urlBuilder->getUrl(
                        'adyen/process/validate3d/', ['_secure' => $this->_getRequest()->isSecure()])
                ]
            ]
        ];

        $methodCode = self::CODE;

        $config = array_merge_recursive($config, [
            'payment' => [
                'ccform' => [
                    'availableTypes' => [$methodCode => $this->getCcAvailableTypes($methodCode)],
                    'months' => [$methodCode => $this->getCcMonths()],
                    'years' => [$methodCode => $this->getCcYears()],
                    'hasVerification' => [$methodCode => $this->hasVerification($methodCode)],
                    'cvvImageUrl' => [$methodCode => $this->getCvvImageUrl()]
                ]
            ]
        ]);

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

        return $config;
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
        $availableTypes = $this->_adyenHelper->getAdyenCcConfigData('cctypes');
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
                $placeholder = $this->_assetSource->findSource($asset);
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

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    protected function getCcMonths()
    {
        return $this->ccConfig->getCcMonths();
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    protected function getCcYears()
    {
        return $this->ccConfig->getCcYears();
    }

    /**
     * Has verification is always true
     *
     * @return bool
     */
    protected function hasVerification()
    {
        return $this->_adyenHelper->getAdyenCcConfigData('useccv');
    }

    /**
     * Retrieve CVV tooltip image url
     *
     * @return string
     */
    protected function getCvvImageUrl()
    {
        return $this->ccConfig->getCvvImageUrl();
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
<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Recurring;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Source;
use Magento\Payment\Model\CcConfig;
use Magento\Store\Model\StoreManagerInterface;

class AdyenCcConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_cc';
    const CC_VAULT_CODE = 'adyen_cc_vault';

    /**
     * @var Data
     */
    protected $_adyenHelper;

    /**
     * @var Source
     */
    protected $_assetSource;

    /**
     * Request object
     *
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var CcConfig
     */
    private $ccConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /** @var Config $configHelper */
    private $configHelper;

    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    /**
     * AdyenCcConfigProvider constructor.
     *
     * @param Data $adyenHelper
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @param Source $assetSource
     * @param StoreManagerInterface $storeManager
     * @param CcConfig $ccConfig
     * @param SerializerInterface $serializer
     * @param Config $configHelper
     */
    public function __construct(
        Data $adyenHelper,
        RequestInterface $request,
        UrlInterface $urlBuilder,
        Source $assetSource,
        StoreManagerInterface $storeManager,
        CcConfig $ccConfig,
        SerializerInterface $serializer,
        Config $configHelper,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_request = $request;
        $this->_urlBuilder = $urlBuilder;
        $this->_assetSource = $assetSource;
        $this->ccConfig = $ccConfig;
        $this->storeManager = $storeManager;
        $this->serializer = $serializer;
        $this->configHelper = $configHelper;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
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
                    'vaultCode' => self::CC_VAULT_CODE,
                    'isActive' => true,
                    'successPage' => $this->_urlBuilder->getUrl(
                        'checkout/onepage/success',
                        ['_secure' => $this->_getRequest()->isSecure()]
                    )
                ]
            ]
        ];

        $methodCode = self::CODE;

        $config = array_merge_recursive(
            $config,
            [
                'payment' => [
                    'ccform' => [
                        'availableTypes' => [
                            $methodCode => $this->paymentMethodsHelper->getCcAvailableTypes()
                        ],
                        'availableTypesByAlt' => [
                            $methodCode => $this->paymentMethodsHelper->getCcAvailableTypesByAlt()
                        ],
                        'months' => [$methodCode => $this->getCcMonths()],
                        'years' => [$methodCode => $this->getCcYears()],
                        'hasVerification' => [$methodCode => $this->hasVerification()],
                        'cvvImageUrl' => [$methodCode => $this->getCvvImageUrl()]
                    ]
                ]
            ]
        );

        $storeId = $this->storeManager->getStore()->getId();
        $recurringEnabled = $this->configHelper->getConfigData('active', Config::XML_ADYEN_ONECLICK, $storeId, true);

        $config['payment']['adyenCc']['methodCode'] = self::CODE;
        $config['payment']['adyenCc']['locale'] = $this->_adyenHelper->getStoreLocale($storeId);
        $config['payment']['adyenCc']['isOneClickEnabled'] = $recurringEnabled;
        $config['payment']['adyenCc']['icons'] = $this->getIcons();
        $config['payment']['adyenCc']['isClickToPayEnabled'] = $this->configHelper->isClickToPayEnabled($storeId);

        // has installments by default false
        $config['payment']['adyenCc']['hasInstallments'] = false;

        // get Installments
        $installmentsEnabled = $this->_adyenHelper->getAdyenCcConfigData('enable_installments');
        $installments = $this->_adyenHelper->getAdyenCcConfigData('installments');

        if ($installmentsEnabled && $installments) {
            $config['payment']['adyenCc']['installments'] = $this->serializer->unserialize($installments);
            $config['payment']['adyenCc']['hasInstallments'] = true;
        } else {
            $config['payment']['adyenCc']['installments'] = [];
        }

        // fetch the config value of require_cvc
        $cvcReq = $this->configHelper->getConfigData(
            'require_cvc',
            Config::XML_ADYEN_CC_VAULT,
            $storeId,
            true);

        $config['payment']['adyenCc']['cvcRequired'] = $cvcReq;

        return $config;
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
     * @return RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }
}

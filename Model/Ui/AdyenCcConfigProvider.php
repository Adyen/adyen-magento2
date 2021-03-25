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

class AdyenCcConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_cc';
    const CC_VAULT_CODE = 'adyen_cc_vault';

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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * AdyenCcConfigProvider constructor.
     *
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\View\Asset\Source $assetSource
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Payment\Model\CcConfig $ccConfig
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\View\Asset\Source $assetSource,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Payment\Model\CcConfig $ccConfig,
        \Magento\Framework\Serialize\SerializerInterface $serializer
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->_request = $request;
        $this->_urlBuilder = $urlBuilder;
        $this->_assetSource = $assetSource;
        $this->ccConfig = $ccConfig;
        $this->storeManager = $storeManager;
        $this->serializer = $serializer;
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
                    'redirectUrl' => $this->_urlBuilder->getUrl(
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
                        'availableTypes' => [$methodCode => $this->getCcAvailableTypes()],
                        'availableTypesByAlt' => [$methodCode => $this->getCcAvailableTypesByAlt()],
                        'months' => [$methodCode => $this->getCcMonths()],
                        'years' => [$methodCode => $this->getCcYears()],
                        'hasVerification' => [$methodCode => $this->hasVerification($methodCode)],
                        'cvvImageUrl' => [$methodCode => $this->getCvvImageUrl()]
                    ]
                ]
            ]
        );

        $enableOneclick = $this->_adyenHelper->getAdyenAbstractConfigData('enable_oneclick');

        $config['payment']['adyenCc']['methodCode'] = self::CODE;

        $config['payment']['adyenCc']['locale'] = $this->_adyenHelper->getStoreLocale(
            $this->storeManager->getStore()->getId()
        );

        $config['payment']['adyenCc']['isOneClickEnabled'] = $enableOneclick;
        $config['payment']['adyenCc']['icons'] = $this->getIcons();


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

        return $config;
    }

    /**
     * Retrieve available credit card types
     *
     * @return array
     */
    protected function getCcAvailableTypes()
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
     * Retrieve available credit card type codes by alt code
     *
     * @return array
     */
    protected function getCcAvailableTypesByAlt()
    {
        $types = [];
        $ccTypes = $this->_adyenHelper->getAdyenCcTypes();
        $availableTypes = $this->_adyenHelper->getAdyenCcConfigData('cctypes');
        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);
            foreach (array_keys($ccTypes) as $code) {
                if (in_array($code, $availableTypes)) {
                    $types[$ccTypes[$code]['code_alt']] = $code;
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

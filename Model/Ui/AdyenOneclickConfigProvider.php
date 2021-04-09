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

use Adyen\Payment\Helper\ChargedCurrency;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

class AdyenOneclickConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_oneclick';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Payment\Model\CcConfig
     */
    private $ccConfig;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * AdyenOneclickConfigProvider constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Payment\Model\CcConfig $ccConfig
     * @param ChargedCurrency $chargedCurrency
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $session,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Payment\Model\CcConfig $ccConfig,
        ChargedCurrency $chargedCurrency
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_request = $request;
        $this->_customerSession = $customerSession;
        $this->_session = $session;
        $this->_storeManager = $storeManager;
        $this->_urlBuilder = $urlBuilder;
        $this->ccConfig = $ccConfig;
        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConfig()
    {
        // set to active
        $config = [
            'payment' => [
                self::CODE => [
                    'isActive' => true,
                    'redirectUrl' => $this->_urlBuilder->getUrl(
                        'checkout/onepage/success',
                        ['_secure' => $this->_getRequest()->isSecure()]
                    )
                ]
            ]
        ];

        // don't show this payment method if vault is enabled
        if ($this->_adyenHelper->isCreditCardVaultEnabled()) {
            $config['payment']['adyenOneclick']['methodCode'] = self::CODE;
            $config['payment'][self::CODE]['isActive'] = false;
            return $config;
        }

        $methodCode = self::CODE;

        $config = array_merge_recursive(
            $config,
            [
                'payment' => [
                    'ccform' => [
                        'availableTypes' => [$methodCode => $this->getCcAvailableTypes()],
                        'months' => [$methodCode => $this->getCcMonths()],
                        'years' => [$methodCode => $this->getCcYears()],
                        'hasVerification' => [$methodCode => $this->hasVerification($methodCode)],
                        'cvvImageUrl' => [$methodCode => $this->getCvvImageUrl()]
                    ]
                ]
            ]
        );

        $config['payment']['adyenOneclick']['methodCode'] = self::CODE;
        $config['payment']['adyenOneclick']['locale'] = $this->_adyenHelper->getStoreLocale(
            $this->_storeManager->getStore()->getId()
        );

        $enableOneclick = $this->_adyenHelper->getAdyenAbstractConfigData('enable_oneclick');
        $canCreateBillingAgreement = false;
        if ($enableOneclick) {
            $canCreateBillingAgreement = true;
        }

        $config['payment']['adyenOneclick']['canCreateBillingAgreement'] = $canCreateBillingAgreement;
        $config['payment']['adyenOneclick']['billingAgreements'] = $this->getAdyenOneclickPaymentMethods();
        $config['payment']['adyenOneclick']['hasCustomerInteraction'] = true;
        return $config;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAdyenOneclickPaymentMethods()
    {
        $billingAgreements = [];
        if ($this->_customerSession->isLoggedIn()) {
            $customerId = $this->_customerSession->getCustomerId();
            $storeId = $this->_storeManager->getStore()->getId();
            $grandTotal = $this->chargedCurrency->getQuoteAmountCurrency($this->_getQuote())->getAmount();

            $billingAgreements = $this->_adyenHelper->getOneClickPaymentMethods(
                $customerId,
                $storeId,
                $grandTotal
            );
        }
        return $billingAgreements;
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    protected function _getQuote()
    {
        return $this->_session->getQuote();
    }

    /**
     * Retrieve availables credit card types
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

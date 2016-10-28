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
     * AdyenOneclickConfigProvider constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\UrlInterface $urlBuilder
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $session,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Payment\Model\CcConfig $ccConfig
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_request = $request;
        $this->_customerSession = $customerSession;
        $this->_session = $session;
        $this->_storeManager = $storeManager;
        $this->_urlBuilder = $urlBuilder;
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

        $config['payment'] ['adyenOneclick']['cseKey'] = $cseKey;
        $config['payment'] ['adyenOneclick']['cseEnabled'] = $cseEnabled;
        $config['payment'] ['adyenOneclick']['cseEnabled'] = $cseEnabled;
        $config['payment']['adyenOneclick']['generationTime'] = date("c");
        $config['payment']['adyenOneclick']['canCreateBillingAgreement'] = $canCreateBillingAgreement;

        $recurringContractType = $this->_getRecurringContractType();

        $config['payment'] ['adyenOneclick']['billingAgreements'] = $this->getAdyenOneclickPaymentMethods();
        if ($recurringContractType == \Adyen\Payment\Model\RecurringType::ONECLICK) {
            $config['payment'] ['adyenOneclick']['hasCustomerInteraction'] = true;
        } else {
            $config['payment'] ['adyenOneclick']['hasCustomerInteraction'] = false;
        }

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
            $grandTotal = $this->_getQuote()->getGrandTotal();
            $recurringType = $this->_getRecurringContractType();

            $billingAgreements = $this->_adyenHelper->getOneClickPaymentMethods(
                $customerId, 
                $storeId, 
                $grandTotal, 
                $recurringType
            );
        }
        return $billingAgreements;
    }

    /**
     * @return mixed
     */
    protected function _getRecurringContractType()
    {
        return $this->_adyenHelper->getAdyenOneclickConfigData('recurring_payment_type');
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
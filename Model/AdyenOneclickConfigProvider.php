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

class AdyenOneclickConfigProvider extends CcGenericConfigProvider
{

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var string[]
     */
    protected $_methodCodes = [
        \Adyen\Payment\Model\Method\Oneclick::METHOD_CODE
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * AdyenOneclickConfigProvider constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Payment\Model\CcConfig $ccConfig
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Payment\Model\CcConfig $ccConfig,
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $session,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($ccConfig, $paymentHelper, $this->_methodCodes);
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->_customerSession = $customerSession;
        $this->_session = $session;
        $this->_appState = $context->getAppState();
        $this->_storeManager = $storeManager;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $config = parent::getConfig();

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

        foreach ($this->_methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {

                $recurringContractType = $this->_getRecurringContractType();

                $config['payment'] ['adyenOneclick']['billingAgreements'] = $this->getAdyenOneclickPaymentMethods();
                if ($recurringContractType == \Adyen\Payment\Model\RecurringType::ONECLICK) {
                    $config['payment'] ['adyenOneclick']['hasCustomerInteraction'] = true;
                } else {
                    $config['payment'] ['adyenOneclick']['hasCustomerInteraction'] = false;
                }
            }
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

            $billingAgreements = $this->_adyenHelper->getOneClickPaymentMethods($customerId, $storeId, $grandTotal, $recurringType);
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
}
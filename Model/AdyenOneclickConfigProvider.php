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
    protected $methodCodes = [
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
     * @var Resource\Billing\Agreement\CollectionFactory
     */
    protected $_billingAgreementCollectionFactory;

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
     * @param \Magento\Payment\Model\CcConfig $ccConfig
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Payment\Model\CcConfig $ccConfig,
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\Resource\Billing\Agreement\CollectionFactory $billingAgreementCollectionFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $session,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($ccConfig, $paymentHelper, $this->methodCodes);
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->_billingAgreementCollectionFactory = $billingAgreementCollectionFactory;
        $this->_customerSession = $customerSession;
        $this->_session = $session;
        $this->_appState = $context->getAppState();
        $this->_storeManager = $storeManager;
    }

    public function getConfig()
    {
        $config = parent::getConfig();

        $demoMode = $this->_adyenHelper->getAdyenAbstractConfigDataFlag('demo_mode');

        if($demoMode) {
            $cseKey = $this->_adyenHelper->getAdyenCcConfigData('cse_publickey_test');
        } else {
            $cseKey = $this->_adyenHelper->getAdyenCcConfigData('cse_publickey_live');
        }

        $cseEnabled = $this->_adyenHelper->getAdyenCcConfigDataFlag('cse_enabled');

        $recurringType = $this->_adyenHelper->getAdyenAbstractConfigData('recurring_type');
        $canCreateBillingAgreement = false;
        if($recurringType == "ONECLICK" || $recurringType == "ONECLICK,RECURRING") {
            $canCreateBillingAgreement = true;
        }


        $config['payment'] ['adyenOneclick']['cseKey'] = $cseKey;
        $config['payment'] ['adyenOneclick']['cseEnabled'] = $cseEnabled;
        $config['payment'] ['adyenOneclick']['cseEnabled'] = $cseEnabled;
        $config['payment']['adyenOneclick']['generationTime'] = date("c");
        $config['payment']['adyenOneclick']['canCreateBillingAgreement'] = $canCreateBillingAgreement;


        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment']['adyenOneclick']['redirectUrl'][$code] = $this->getMethodRedirectUrl($code);
                $config['payment'] ['adyenOneclick']['billingAgreements'] = $this->getAdyenOneclickPaymentMethods();

                $recurringContractType = $this->_getRecurringContractType();
                $config['payment'] ['adyenOneclick']['recurringContractType'] = $recurringContractType;
                if($recurringContractType == \Adyen\Payment\Model\RecurringType::ONECLICK) {
                    $config['payment'] ['adyenOneclick']['hasCustomerInteraction'] = true;
                } else {
                    $config['payment'] ['adyenOneclick']['hasCustomerInteraction'] = false;
                }
                $config['payment']['adyenOneclick']['redirectUrl'][$code] = $this->getMethodRedirectUrl($code);
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

    public function getAdyenOneclickPaymentMethods()
    {

        $billingAgreements = [];

        if ($this->_customerSession->isLoggedIn()) {


            $customerId = $this->_customerSession->getCustomerId();

            // is admin?
            if ($this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
                //retrieve storeId from quote
                $store = $this->_session->getQuote()->getStore();
            } else {
                $store = $this->_storeManager->getStore();
            }

            $baCollection = $this->_billingAgreementCollectionFactory->create();
            $baCollection->addFieldToFilter('customer_id', $customerId);
            $baCollection->addFieldToFilter('store_id', $store->getId());
            $baCollection->addFieldToFilter('method_code', 'adyen_oneclick');
            $baCollection->addActiveFilter();

            $recurringPaymentType = $this->_getRecurringContractType();

            foreach ($baCollection as $billingAgreement) {

                $agreementData = $billingAgreement->getAgreementData();
                // check if AgreementLabel is set and if contract has an recurringType

                if($billingAgreement->getAgreementLabel()) {
                    $data = ['reference_id' => $billingAgreement->getReferenceId(),
                        'agreement_label' => $billingAgreement->getAgreementLabel(),
                        'agreement_data' => $agreementData
                    ];
                    $billingAgreements[] = $data;
                }
            }
        }
        return $billingAgreements;
    }



    /**
     * @param Adyen_Payment_Model_Billing_Agreement $billingAgreement
     * @param Mage_Core_Model_Store                 $store
     *
     * @return bool
     */
    protected function _createPaymentMethodFromBA($billingAgreement, $store)
    {
        $methodInstance = $billingAgreement->getPaymentMethodInstance();
        if (! $methodInstance || ! $methodInstance->getConfigData('active', $store)) {
            return false;
        }

        $methodNewCode = 'adyen_oneclick_'.$billingAgreement->getReferenceId();

        $methodData = array('model' => 'adyen/adyen_oneclick')
            + $billingAgreement->getOneClickData()
            + Mage::getStoreConfig('payment/adyen_oneclick', $store);

        foreach ($methodData as $key => $value) {
            $store->setConfig('payment/'.$methodNewCode.'/'.$key, $value);
        }

        return true;
    }

    protected function _getRecurringContractType()
    {
        return $this->_adyenHelper->getAdyenOneclickConfigData('recurring_payment_type');
    }
}
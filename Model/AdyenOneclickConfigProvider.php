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
     * @var AdyenGenericConfig
     */
    protected $_genericConfig;


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
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Adyen\Payment\Model\AdyenGenericConfig $genericConfig
    ) {
        parent::__construct($ccConfig, $paymentHelper, $this->methodCodes);
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->_billingAgreementCollectionFactory = $billingAgreementCollectionFactory;
        $this->_customerSession = $customerSession;
        $this->_session = $session;
        $this->_appState = $context->getAppState();
        $this->_storeManager = $storeManager;
        $this->_genericConfig = $genericConfig;
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

                $recurringContractType = $this->_getRecurringContractType();

                $config['payment'] ['adyenOneclick']['billingAgreements'] = $this->getAdyenOneclickPaymentMethods();
                $config['payment'] ['adyenOneclick']['recurringContractType'] = $recurringContractType;
                if($recurringContractType == \Adyen\Payment\Model\RecurringType::ONECLICK) {
                    $config['payment'] ['adyenOneclick']['hasCustomerInteraction'] = true;
                } else {
                    $config['payment'] ['adyenOneclick']['hasCustomerInteraction'] = false;
                }
            }
        }
        return $config;
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

                // no agreementData and contractType then ignore
                if((!is_array($agreementData)) || (!isset($agreementData['contractTypes']))) {
                    continue;
                }

                // check if contractType is supporting the selected contractType for OneClick payments
                $allowedContractTypes = $agreementData['contractTypes'];
                if(in_array($recurringPaymentType, $allowedContractTypes)) {
                    // check if AgreementLabel is set and if contract has an recurringType
                    if($billingAgreement->getAgreementLabel()) {
                        $data = ['reference_id' => $billingAgreement->getReferenceId(),
                            'agreement_label' => $billingAgreement->getAgreementLabel(),
                            'agreement_data' => $agreementData
                        ];

                        if($this->_genericConfig->showLogos()) {

                            $logoName = $agreementData['variant'];
                            // for Ideal use sepadirectdebit because it is
                            if($agreementData['variant'] == 'ideal') {
                                $logoName = "sepadirectdebit";
                            }

                            $asset = $this->_genericConfig->createAsset('Adyen_Payment::images/logos/' . $logoName . '.png');
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
                            $data['logo'] = $icon;

                        }

                        $billingAgreements[] = $data;
                    }
                }
            }
        }
        return $billingAgreements;
    }

    protected function _getRecurringContractType()
    {
        return $this->_adyenHelper->getAdyenOneclickConfigData('recurring_payment_type');
    }
}
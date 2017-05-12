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
namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

class RecurringDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * RecurringDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\Model\Context $context
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Model\Context $context
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->appState = $context->getAppState();
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $result = [];

        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        // Needs to change when oneclick,cc using facade impl.
        $paymentMethodCode = $payment->getMethodInstance()->getCode();
        $customerId = $payment->getOrder()->getCustomerId();

        $storeId = null;
        if ($this->appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            $storeId = $payment->getOrder()->getStoreId();
        }
        $recurringType = $this->adyenHelper->getAdyenAbstractConfigData('recurring_type', $storeId);
        
        // set the recurring type
        $recurringContractType = null;
        if ($recurringType) {
            if ($paymentMethodCode == \Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider::CODE) {
                /*
                 * For ONECLICK look at the recurringPaymentType that the merchant
                 * has selected in Adyen ONECLICK settings
                 */
                if ($payment->getAdditionalInformation('customer_interaction')) {
                    $recurringContractType = \Adyen\Payment\Model\RecurringType::ONECLICK;
                } else {
                    $recurringContractType =  \Adyen\Payment\Model\RecurringType::RECURRING;
                }
            } else if ($paymentMethodCode == \Adyen\Payment\Model\Ui\AdyenCcConfigProvider::CODE) {
                if ($payment->getAdditionalInformation("store_cc") == "" &&
                    ($recurringType == "ONECLICK,RECURRING" || $recurringType == "RECURRING")) {
                    $recurringContractType = \Adyen\Payment\Model\RecurringType::RECURRING;
                } elseif ($payment->getAdditionalInformation("store_cc") == "1") {
                    $recurringContractType = $recurringType;
                }
            } else {
                $recurringContractType = $recurringType;
            }
        }

        // only when recurringContractType is set and when a customer is loggedIn
        if ($recurringContractType && $customerId > 0) {
            $recurring = ['contract' => $recurringContractType];
            $result['recurring'] = $recurring;
        }

        return $result;
    }
}
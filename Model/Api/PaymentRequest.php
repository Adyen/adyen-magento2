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

namespace Adyen\Payment\Model\Api;

use Magento\Framework\DataObject;

class PaymentRequest extends DataObject
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var \Adyen\Client
     */
    protected $_client;

    /**
     * @var \Adyen\Payment\Model\RecurringType
     */
    protected $_recurringType;

    const GUEST_ID = 'customer_';

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Model\RecurringType $recurringType
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\RecurringType $recurringType,
        array $data = []
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_encryptor = $encryptor;
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->_recurringType = $recurringType;

        // initialize client
        $webserviceUsername = $this->_adyenHelper->getWsUsername();
        $webservicePassword = $this->_adyenHelper->getWsPassword();

        $client = new \Adyen\Client();
        $client->setApplicationName("Magento 2 plugin");
        $client->setUsername($webserviceUsername);
        $client->setPassword($webservicePassword);

        if($this->_adyenHelper->isDemoMode()) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            $client->setEnvironment(\Adyen\Environment::LIVE);
        }

        // assign magento log
        $client->setLogger($adyenLogger);

        $this->_client = $client;

    }

    public function fullApiRequest($payment, $paymentMethodCode)
    {
        $order = $payment->getOrder();
        $amount = $order->getGrandTotal();
        $customerEmail = $order->getCustomerEmail();
        $shopperIp = $order->getRemoteIp();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account");
        $recurringType = $this->_adyenHelper->getAdyenAbstractConfigData('recurring_type');
        $realOrderId = $order->getRealOrderId();

        $customerId = $order->getCustomerId();
        $shopperReference = (!empty($customerId)) ? $customerId : self::GUEST_ID . $realOrderId;

        // call lib
        $service = new \Adyen\Service\Payment($this->_client);

        $amount = ['currency' => $orderCurrencyCode, 'value' => $this->_adyenHelper->formatAmount($amount, $orderCurrencyCode)];
        $browserInfo = ['userAgent' => $_SERVER['HTTP_USER_AGENT'], 'acceptHeader' => $_SERVER['HTTP_ACCEPT']];

        $request = array(
            "merchantAccount" => $merchantAccount,
            "amount" => $amount,
            "reference" => $order->getIncrementId(),
            "shopperIP" => $shopperIp,
            "shopperEmail" => $customerEmail,
            "shopperReference" => $shopperReference,
            "fraudOffset" => "0",
            "browserInfo" => $browserInfo
        );


        // set the recurring type
        $recurringContractType = null;
        if($recurringType) {
            if($paymentMethodCode == \Adyen\Payment\Model\Method\Oneclick::METHOD_CODE) {
                // For ONECLICK look at the recurringPaymentType that the merchant has selected in Adyen ONECLICK settings
                if($payment->getAdditionalInformation('customer_interaction')) {
                    $recurringContractType = \Adyen\Payment\Model\RecurringType::ONECLICK;
                } else {
                    $recurringContractType =  \Adyen\Payment\Model\RecurringType::RECURRING;
                }
            } else if($paymentMethodCode == \Adyen\Payment\Model\Method\Cc::METHOD_CODE) {
                if($payment->getAdditionalInformation("store_cc") == "" && ($recurringType == "ONECLICK,RECURRING" || $recurringType == "RECURRING")) {
                    $recurringContractType = \Adyen\Payment\Model\RecurringType::RECURRING;
                } elseif($payment->getAdditionalInformation("store_cc") == "1") {
                    $recurringContractType = $recurringType;
                }
            } else {
                $recurringContractType = $recurringType;
            }
        }

        if($recurringContractType)
        {
            $recurring = array('contract' => $recurringContractType);
            $request['recurring'] = $recurring;
        }

        $this->_adyenLogger->error('storeCC?:' . $payment->getAdditionalInformation("store_cc"));
        $this->_adyenLogger->error('recuringtype' . $recurringType);
        $this->_adyenLogger->error('recurringcontractType' . $recurringContractType);


        $billingAddress = $order->getBillingAddress();

        if($billingAddress)
        {
            $addressArray = $this->_adyenHelper->getStreet($billingAddress);

            $requestBilling = array("street" => $addressArray['name'],
                "postalCode" => $billingAddress->getPostcode(),
                "city" => $billingAddress->getCity(),
                "houseNumberOrName" => $addressArray['house_number'],
                "stateOrProvince" => $billingAddress->getRegionCode(),
                "country" => $billingAddress->getCountryId()
            );

            // houseNumberOrName is mandatory
            if($requestBilling['houseNumberOrName'] == "") {
                $requestBilling['houseNumberOrName'] = "NA";
            }

            $requestBilling['billingAddress'] = $requestBilling;


            $request = array_merge($request, $requestBilling);
        }

        $deliveryAddress = $order->getDeliveryAddress();
        if($deliveryAddress)
        {
            $addressArray = $this->_adyenHelper->getStreet($deliveryAddress);

            $requestDelivery = array("street" => $addressArray['name'],
                "postalCode" => $deliveryAddress->getPostcode(),
                "city" => $deliveryAddress->getCity(),
                "houseNumberOrName" => $addressArray['house_number'],
                "stateOrProvince" => $deliveryAddress->getRegionCode(),
                "country" => $deliveryAddress->getCountryId()
            );

            // houseNumberOrName is mandatory
            if($requestDelivery['houseNumberOrName'] == "") {
                $requestDelivery['houseNumberOrName'] = "NA";
            }

            $requestDelivery['deliveryAddress'] = $requestDelivery;
            $request = array_merge($request, $requestDelivery);
        }

        // define the shopper interaction
        if($paymentMethodCode == \Adyen\Payment\Model\Method\Oneclick::METHOD_CODE) {
            $recurringDetailReference = $payment->getAdditionalInformation("recurring_detail_reference");
            if($payment->getAdditionalInformation('customer_interaction')) {
                $shopperInteraction = "Ecommerce";
            } else {
                $shopperInteraction = "ContAuth";
            }

            // For recurring Ideal and Sofort needs to be converted to SEPA for this it is mandatory to set selectBrand to sepadirectdebit
            if(!$payment->getAdditionalInformation('customer_interaction')) {
                if($payment->getCcType() == "directEbanking" || $payment->getCcType() == "ideal") {
                    $this->selectedBrand = "sepadirectdebit";
                }
            }
        } else {
            $recurringDetailReference = null;
            $shopperInteraction = "Ecommerce";
        }

        if($shopperInteraction) {
            $request['shopperInteraction'] = $shopperInteraction;
        }

        if($recurringDetailReference && $recurringDetailReference != "") {
            $request['selectedRecurringDetailReference'] = $recurringDetailReference;
        }

        // If cse is enabled add encrypted card date into request
        if($this->_adyenHelper->getAdyenCcConfigDataFlag('cse_enabled')) {
            $request['additionalData']['card.encrypted.json'] = $payment->getAdditionalInformation("encrypted_data");
        } else {
            $requestCreditCardDetails = array(
                "expiryMonth" => $payment->getCcExpMonth(),
                "expiryYear" => $payment->getCcExpYear(),
                "holderName" => $payment->getCcOwner(),
                "number" => $payment->getCcNumber(),
                "cvc" => $payment->getCcCid(),
            );
            $cardDetails['card'] = $requestCreditCardDetails;
            $request = array_merge($request, $cardDetails);
        }

        $result = $service->authorise($request);

        return $result;
    }

    public function authorise3d($payment)
    {
        $order = $payment->getOrder();
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account");
        $shopperIp = $order->getRemoteIp();

        $md = $payment->getAdditionalInformation('md');
        $paResponse = $payment->getAdditionalInformation('paResponse');

        $browserInfo = ['userAgent' => $_SERVER['HTTP_USER_AGENT'], 'acceptHeader' => $_SERVER['HTTP_ACCEPT']];
        $request = array(
            "merchantAccount" => $merchantAccount,
            "browserInfo" => $browserInfo,
            "md" => $md,
            "paResponse" => $paResponse,
            "shopperIP" => $shopperIp
        );

        try {
            $service = new \Adyen\Service\Payment($this->_client);
            $result = $service->authorise3D($request);
        } catch(Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('3D secure failed'));
        }

        return $result;
    }

    /**
     * Capture payment on Adyen
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $pspReference = $this->_getPspReference($payment);
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account");
        $currency = $payment->getOrder()->getBaseCurrencyCode();

        //format the amount to minor units
        $amount = $this->_adyenHelper->formatAmount($amount, $currency);

        $modificationAmount = array('currency' => $currency, 'value' => $amount);

        $request = array(
            "merchantAccount" => $merchantAccount,
            "modificationAmount" => $modificationAmount,
            "reference" => $payment->getOrder()->getIncrementId(),
            "originalReference" => $pspReference
        );

        // call lib
        $service = new \Adyen\Service\Modification($this->_client);
        $result = $service->capture($request);

        if($result['response'] != '[capture-received]') {
            // something went wrong
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action failed'));
        }

        // set pspReference as TransactionId so you can do an online refund
        if(isset($result['pspReference'])) {
            $payment->setTransactionId($result['pspReference'])
                ->setIsTransactionClosed(false);
        }

        return $result;
    }

    /**
     * Cancel or Refund payment on Adyen
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function cancelOrRefund(\Magento\Payment\Model\InfoInterface $payment)
    {
        $pspReference = $this->_getPspReference($payment);
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account");

        $request = array(
            "merchantAccount" => $merchantAccount,
            "reference" => $payment->getOrder()->getIncrementId(),
            "originalReference" => $pspReference
        );

        // call lib
        $service = new \Adyen\Service\Modification($this->_client);
        $result = $service->cancelOrRefund($request);

        if($result['response'] != '[cancelOrRefund-received]') {
            // something went wrong
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action failed'));
        }

        return $result;
    }

    /**
     * (partial)Refund payment on Adyen
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $pspReference = $this->_getPspReference($payment);
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account");
        $currency = $payment->getOrder()->getBaseCurrencyCode();

        //format the amount to minor units
        $amount = $this->_adyenHelper->formatAmount($amount, $currency);

        $modificationAmount = array('currency' => $currency, 'value' => $amount);

        $request = array(
            "merchantAccount" => $merchantAccount,
            "modificationAmount" => $modificationAmount,
            "reference" => $payment->getOrder()->getIncrementId(),
            "originalReference" => $pspReference
        );

        // call lib
        $service = new \Adyen\Service\Modification($this->_client);
        $result = $service->refund($request);

        if($result['response'] != '[refund-received]') {
            // something went wrong
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action failed'));
        }

        return $result;
    }

    public function getRecurringContractsForShopper($shopperReference, $storeId)
    {
        $recurringContracts = array();
        $recurringTypes = $this->_recurringType->getAllowedRecurringTypesForListRecurringCall();

        foreach ($recurringTypes as $recurringType) {

            try {
                // merge ONECLICK and RECURRING into one record with recurringType ONECLICK,RECURRING
                $listRecurringContractByType = $this->listRecurringContractByType($shopperReference, $storeId, $recurringType);
                if(isset($listRecurringContractByType['details'] ))
                {
                    foreach($listRecurringContractByType['details'] as $recurringContractDetails) {
                        if(isset($recurringContractDetails['RecurringDetail'])) {
                            $recurringContract = $recurringContractDetails['RecurringDetail'];

                            if(isset($recurringContract['recurringDetailReference'])) {
                                $recurringDetailReference = $recurringContract['recurringDetailReference'];
                                // check if recurring reference is already in array
                                if(isset($recurringContracts[$recurringDetailReference])) {
                                    // recurring reference already exists so recurringType is possible for ONECLICK and RECURRING
                                    $recurringContracts[$recurringDetailReference]['recurring_type']= "ONECLICK,RECURRING";
                                } else {
                                    $recurringContracts[$recurringDetailReference] = $recurringContract;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $exception) {
                print_r($exception);
            }
        }
        return $recurringContracts;
    }


    public function listRecurringContractByType($shopperReference, $storeId, $recurringType)
    {
        // rest call to get list of recurring details
        $contract = ['contract' => $recurringType];
        $request = array(
            "merchantAccount"    => $this->_adyenHelper->getAdyenAbstractConfigData('merchant_account', $storeId),
            "shopperReference"   => $shopperReference,
            "recurring" => $contract,
        );

        // call lib
        $service = new \Adyen\Service\Recurring($this->_client);
        $result = $service->listRecurringDetails($request);

        return $result;
    }

    /**
     * Disable a recurring contract
     *
     * @param string                         $recurringDetailReference
     * @param string                         $shopperReference
     * @param int|Mage_Core_model_Store|null $store
     *
     * @throws Adyen_Payment_Exception
     * @return bool
     */
    public function disableRecurringContract($recurringDetailReference, $shopperReference)
    {
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account");

        $request = array(
            "merchantAccount" => $merchantAccount,
            "shopperReference" => $shopperReference,
            "recurringDetailReference" => $recurringDetailReference
        );

        // call lib
        $service = new \Adyen\Service\Recurring($this->_client);

        try {
            $result = $service->disable($request);
        } catch(\Exception $e) {
            $this->_adyenLogger->info($e->getMessage());
        }

        if(isset($result['response']) && $result['response'] == '[detail-successfully-disabled]') {
            return true;
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('Failed to disable this contract'));
        }
    }

    /**
     * Retrieve pspReference from payment object
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     */
    protected function _getPspReference(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $payment->getAdyenPspReference();
    }

    /**
     * Decrypt password
     *
     * @param   string $password
     * @return  string
     */
    public function decryptPassword($password)
    {
        return $this->_encryptor->decrypt($password);
    }
}
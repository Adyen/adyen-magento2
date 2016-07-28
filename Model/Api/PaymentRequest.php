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

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    const GUEST_ID = 'customer_';

    /**
     * PaymentRequest constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Model\RecurringType $recurringType
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\RecurringType $recurringType,
        array $data = []
    ) {
        $this->_encryptor = $encryptor;
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->_recurringType = $recurringType;
        $this->_appState = $context->getAppState();

        // initialize client
        $webserviceUsername = $this->_adyenHelper->getWsUsername();
        $webservicePassword = $this->_adyenHelper->getWsPassword();

        $client = new \Adyen\Client();
        $client->setApplicationName("Magento 2 plugin");
        $client->setUsername($webserviceUsername);
        $client->setPassword($webservicePassword);

        if ($this->_adyenHelper->isDemoMode()) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            $client->setEnvironment(\Adyen\Environment::LIVE);
        }

        // assign magento log
        $client->setLogger($adyenLogger);

        $this->_client = $client;
    }

    /**
     * @param $payment
     * @param $paymentMethodCode
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function fullApiRequest($payment, $paymentMethodCode)
    {
        $storeId = null;
        if ($this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            $storeId = $payment->getOrder()->getStoreId();
        }

        $order = $payment->getOrder();
        $amount = $order->getGrandTotal();
        $customerEmail = $order->getCustomerEmail();
        $shopperIp = $order->getRemoteIp();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);
        $recurringType = $this->_adyenHelper->getAdyenAbstractConfigData('recurring_type', $storeId);
        $realOrderId = $order->getRealOrderId();

        $customerId = $order->getCustomerId();
        $shopperReference = (!empty($customerId)) ? $customerId : self::GUEST_ID . $realOrderId;

        // call lib
        $service = new \Adyen\Service\Payment($this->_client);

        $amount = ['currency' => $orderCurrencyCode,
            'value' => $this->_adyenHelper->formatAmount($amount, $orderCurrencyCode)];
        $browserInfo = ['userAgent' => $_SERVER['HTTP_USER_AGENT'], 'acceptHeader' => $_SERVER['HTTP_ACCEPT']];

        $request = [
            "merchantAccount" => $merchantAccount,
            "amount" => $amount,
            "reference" => $order->getIncrementId(),
            "shopperIP" => $shopperIp,
            "shopperEmail" => $customerEmail,
            "shopperReference" => $shopperReference,
            "fraudOffset" => "0",
            "browserInfo" => $browserInfo
        ];

        // set the recurring type
        $recurringContractType = null;
        if ($recurringType) {
            $recurringContractType = $recurringType;
        }

        if ($recurringContractType) {
            $recurring = ['contract' => $recurringContractType];
            $request['recurring'] = $recurring;
        }

        $billingAddress = $order->getBillingAddress();

        if ($billingAddress) {
            $addressArray = $this->_adyenHelper->getStreet($billingAddress);

            $requestBilling = ["street" => $addressArray['name'],
                "postalCode" => $billingAddress->getPostcode(),
                "city" => $billingAddress->getCity(),
                "houseNumberOrName" => $addressArray['house_number'],
                "stateOrProvince" => $billingAddress->getRegionCode(),
                "country" => $billingAddress->getCountryId()
            ];

            // houseNumberOrName is mandatory
            if ($requestBilling['houseNumberOrName'] == "") {
                $requestBilling['houseNumberOrName'] = "NA";
            }

            $requestBilling['billingAddress'] = $requestBilling;
            $request = array_merge($request, $requestBilling);
        }

        $deliveryAddress = $order->getDeliveryAddress();
        if($deliveryAddress) {
            $addressArray = $this->_adyenHelper->getStreet($deliveryAddress);

            $requestDelivery = ["street" => $addressArray['name'],
                "postalCode" => $deliveryAddress->getPostcode(),
                "city" => $deliveryAddress->getCity(),
                "houseNumberOrName" => $addressArray['house_number'],
                "stateOrProvince" => $deliveryAddress->getRegionCode(),
                "country" => $deliveryAddress->getCountryId()
            ];

            // houseNumberOrName is mandatory
            if ($requestDelivery['houseNumberOrName'] == "") {
                $requestDelivery['houseNumberOrName'] = "NA";
            }

            $requestDelivery['deliveryAddress'] = $requestDelivery;
            $request = array_merge($request, $requestDelivery);
        }

        $recurringDetailReference = null;

        // define the shopper interaction
        $shopperInteraction = "Ecommerce";


        if ($shopperInteraction) {
            $request['shopperInteraction'] = $shopperInteraction;
        }

        if ($recurringDetailReference && $recurringDetailReference != "") {
            $request['selectedRecurringDetailReference'] = $recurringDetailReference;
        }

        // if it is a sepadirectdebit set selectedBrand to sepadirectdebit in the case of oneclick
        if ($payment->getCcType() == "sepadirectdebit") {
            $request['selectedBrand'] = "sepadirectdebit";
        }
        

        $result = $service->authorise($request);
        return $result;
    }

    /**
     * @param $payment
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorise3d($payment)
    {
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);
        $shopperIp = $order->getRemoteIp();

        $md = $payment->getAdditionalInformation('md');
        $paResponse = $payment->getAdditionalInformation('paResponse');

        $browserInfo = ['userAgent' => $_SERVER['HTTP_USER_AGENT'], 'acceptHeader' => $_SERVER['HTTP_ACCEPT']];
        $request = [
            "merchantAccount" => $merchantAccount,
            "browserInfo" => $browserInfo,
            "md" => $md,
            "paResponse" => $paResponse,
            "shopperIP" => $shopperIp
        ];

        try {
            $service = new \Adyen\Service\Payment($this->_client);
            $result = $service->authorise3D($request);
        } catch(\Adyen\AdyenException $e) {
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
        $storeId = $payment->getOrder()->getStoreId();
        $pspReference = $this->_getPspReference($payment);
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);
        $currency = $payment->getOrder()->getBaseCurrencyCode();

        //format the amount to minor units
        $amount = $this->_adyenHelper->formatAmount($amount, $currency);

        $modificationAmount = ['currency' => $currency, 'value' => $amount];

        $request = [
            "merchantAccount" => $merchantAccount,
            "modificationAmount" => $modificationAmount,
            "reference" => $payment->getOrder()->getIncrementId(),
            "originalReference" => $pspReference
        ];

        // call lib
        $service = new \Adyen\Service\Modification($this->_client);
        $result = $service->capture($request);

        if ($result['response'] != '[capture-received]') {
            // something went wrong
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action failed'));
        }

        // save pspreference in additional Data to check for notification if refund is triggerd from inside Magento
        $payment->setAdditionalInformation('capture_pspreference', $result['pspReference']);

        // set pspReference as TransactionId so you can do an online refund
        if (isset($result['pspReference'])) {
            $payment->setTransactionId($result['pspReference'])
                ->setIsTransactionClosed(false)
                ->setParentTransactionId($payment->getAdditionalInformation('pspReference'));
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
        $storeId = $payment->getOrder()->getStoreId();
        $pspReference = $this->_getPspReference($payment);
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);

        $request = [
            "merchantAccount" => $merchantAccount,
            "reference" => $payment->getOrder()->getIncrementId(),
            "originalReference" => $pspReference
        ];

        // call lib
        $service = new \Adyen\Service\Modification($this->_client);
        $result = $service->cancelOrRefund($request);

        if ($result['response'] != '[cancelOrRefund-received]') {
            // something went wrong
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action failed'));
        }

        // set pspReference as TransactionId so you can do an online refund
        if (isset($result['pspReference'])) {
            $payment->setTransactionId($result['pspReference'])
                ->setIsTransactionClosed(false)
                ->setParentTransactionId($payment->getAdditionalInformation('pspReference'));
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
        $storeId = $payment->getOrder()->getStoreId();
        $pspReference = $this->_getPspReference($payment);
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);
        $currency = $payment->getOrder()->getBaseCurrencyCode();

        //format the amount to minor units
        $amount = $this->_adyenHelper->formatAmount($amount, $currency);

        $modificationAmount = ['currency' => $currency, 'value' => $amount];

        $request = [
            "merchantAccount" => $merchantAccount,
            "modificationAmount" => $modificationAmount,
            "reference" => $payment->getOrder()->getIncrementId(),
            "originalReference" => $pspReference
        ];

        // call lib
        $service = new \Adyen\Service\Modification($this->_client);
        $result = $service->refund($request);

        if ($result['response'] != '[refund-received]') {
            // something went wrong
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action failed'));
        }

        // set pspReference as TransactionId so you can do an online refund
        if (isset($result['pspReference'])) {
            $payment->setTransactionId($result['pspReference'])
                ->setIsTransactionClosed(false)
                ->setParentTransactionId($payment->getAdditionalInformation('pspReference'));
        }

        return $result;
    }

    /**
     * @param $shopperReference
     * @param $storeId
     * @return array
     * @throws \Exception
     */
    public function getRecurringContractsForShopper($shopperReference, $storeId)
    {
        $recurringContracts = [];
        $recurringTypes = $this->_recurringType->getAllowedRecurringTypesForListRecurringCall();

        foreach ($recurringTypes as $recurringType) {

            try {
                // merge ONECLICK and RECURRING into one record with recurringType ONECLICK,RECURRING
                $listRecurringContractByType =
                    $this->listRecurringContractByType($shopperReference, $storeId, $recurringType);

                if (isset($listRecurringContractByType['details'])) {
                    foreach ($listRecurringContractByType['details'] as $recurringContractDetails) {
                        if (isset($recurringContractDetails['RecurringDetail'])) {
                            $recurringContract = $recurringContractDetails['RecurringDetail'];

                            if (isset($recurringContract['recurringDetailReference'])) {
                                $recurringDetailReference = $recurringContract['recurringDetailReference'];
                                // check if recurring reference is already in array
                                if (isset($recurringContracts[$recurringDetailReference])) {
                                    /*
                                     * recurring reference already exists so recurringType is possible
                                     * for ONECLICK and RECURRING
                                     */
                                    $recurringContracts[$recurringDetailReference]['recurring_type'] =
                                        "ONECLICK,RECURRING";
                                } else {
                                    $recurringContracts[$recurringDetailReference] = $recurringContract;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $exception) {
                // log exception
                $this->_adyenLogger->addError($exception);
                throw($exception);
            }
        }
        return $recurringContracts;
    }

    /**
     * @param $shopperReference
     * @param $storeId
     * @param $recurringType
     * @return mixed
     */
    public function listRecurringContractByType($shopperReference, $storeId, $recurringType)
    {
        // rest call to get list of recurring details
        $contract = ['contract' => $recurringType];
        $request = [
            "merchantAccount"    => $this->_adyenHelper->getAdyenAbstractConfigData('merchant_account', $storeId),
            "shopperReference"   => $shopperReference,
            "recurring" => $contract,
        ];

        // call lib
        $service = new \Adyen\Service\Recurring($this->_client);
        $result = $service->listRecurringDetails($request);

        return $result;
    }

    /**
     * Disable a recurring contract
     *
     * @param $recurringDetailReference
     * @param $shopperReference
     * @param $storeId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function disableRecurringContract($recurringDetailReference, $shopperReference, $storeId)
    {
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account", $storeId);

        $request = [
            "merchantAccount" => $merchantAccount,
            "shopperReference" => $shopperReference,
            "recurringDetailReference" => $recurringDetailReference
        ];

        // call lib
        $service = new \Adyen\Service\Recurring($this->_client);

        try {
            $result = $service->disable($request);
        } catch(\Exception $e) {
            $this->_adyenLogger->info($e->getMessage());
        }

        if (isset($result['response']) && $result['response'] == '[detail-successfully-disabled]') {
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
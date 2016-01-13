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
//use \Adyen\Client;

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
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        array $data = []
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_encryptor = $encryptor;
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;

        // initialize client
        $webserviceUsername = $this->_adyenHelper->getWsUsername();
        $webservicePassword = $this->_adyenHelper->getWsPassword();

        $client = new \Adyen\Client();
        $client->setApplicationName("Magento 2 plugin");
        $client->setUsername($webserviceUsername);
        $client->setPassword($webservicePassword);

        if($this->_adyenHelper->isDemoMode()) {
            $client->setModus("test");
        } else {
            $client->setModus("live");
        }

        // assign magento log
        $client->setLogger($adyenLogger);

        $this->_client = $client;

    }

    public function fullApiRequest($payment)
    {
        $order = $payment->getOrder();
        $amount = $order->getGrandTotal();
        $customerEmail = $order->getCustomerEmail();
        $shopperIp = $order->getRemoteIp();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account");

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
            "shopperReference" => $order->getIncrementId(),
            "fraudOffset" => "0",
            "browserInfo" => $browserInfo
        );

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
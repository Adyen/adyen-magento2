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
    }

    public function fullApiRequest($payment)
    {
        $order = $payment->getOrder();
        $amount = $order->getGrandTotal();
        $customerEmail = $order->getCustomerEmail();
        $shopperIp = $order->getRemoteIp();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account");

        $request = array(
            "action" => "Payment.authorise",
            "paymentRequest.merchantAccount" => $merchantAccount,
            "paymentRequest.amount.currency" => $orderCurrencyCode,
            "paymentRequest.amount.value" => $this->_adyenHelper->formatAmount($amount, $orderCurrencyCode),
            "paymentRequest.reference" => $order->getIncrementId(),
            "paymentRequest.shopperIP" => $shopperIp,
            "paymentRequest.shopperEmail" => $customerEmail,
            "paymentRequest.shopperReference" => $order->getIncrementId(),
            "paymentRequest.fraudOffset" => "0",
            "paymentRequest.browserInfo.userAgent" => $_SERVER['HTTP_USER_AGENT'],
            "paymentRequest.browserInfo.acceptHeader" => $_SERVER['HTTP_ACCEPT']
        );

        $billingAddress = $order->getBillingAddress();

        if($billingAddress)
        {
            $addressArray = $this->_adyenHelper->getStreet($billingAddress);
            $requestBilling = array("paymentRequest.card.billingAddress.street" => $addressArray['name'],
                "paymentRequest.card.billingAddress.postalCode" => $billingAddress->getPostcode(),
                "paymentRequest.card.billingAddress.city" => $billingAddress->getCity(),
                "paymentRequest.card.billingAddress.houseNumberOrName" => $addressArray['house_number'],
                "paymentRequest.card.billingAddress.stateOrProvince" => $billingAddress->getRegionCode(),
                "paymentRequest.card.billingAddress.country" => $billingAddress->getCountryId()
            );
            $request = array_merge($request, $requestBilling);
        }

        $deliveryAddress = $order->getDeliveryAddress();
        if($deliveryAddress)
        {
            $addressArray = $this->_adyenHelper->getStreet($deliveryAddress);

            $requestDelivery = array("paymentRequest.card.deliveryAddress.street" => $addressArray['name'],
                "paymentRequest.card.deliveryAddress.postalCode" => $deliveryAddress->getPostcode(),
                "paymentRequest.card.deliveryAddress.city" => $deliveryAddress->getCity(),
                "paymentRequest.card.deliveryAddress.houseNumberOrName" => $addressArray['house_number'],
                "paymentRequest.card.deliveryAddress.stateOrProvince" => $deliveryAddress->getRegionCode(),
                "paymentRequest.card.deliveryAddress.country" => $deliveryAddress->getCountryId()
            );
            $request = array_merge($request, $requestDelivery);
        }


        // If cse is enabled add encrypted card date into request
        if($this->_adyenHelper->getAdyenCcConfigDataFlag('cse_enabled')) {
            $request['paymentRequest.additionalData.card.encrypted.json'] = $payment->getAdditionalInformation("encrypted_data");
        } else {
            $requestCreditCardDetails = array("paymentRequest.card.expiryMonth" => $payment->getCcExpMonth(),
                "paymentRequest.card.expiryYear" => $payment->getCcExpYear(),
                "paymentRequest.card.holderName" => $payment->getCcOwner(),
                "paymentRequest.card.number" => $payment->getCcNumber(),
                "paymentRequest.card.cvc" => $payment->getCcCid(),
            );
            $request = array_merge($request, $requestCreditCardDetails);
        }
        return $this->_apiRequest($request);
    }


    protected function _apiRequest($request)
    {
        // log the request
        $this->_adyenLogger->info('The request to adyen: ' . print_r($request, true));


        $webserviceUsername = $this->_adyenHelper->getWsUsername();
        $webservicePassword = $this->_adyenHelper->getWsPassword();
        $url = $this->_adyenHelper->getWsUrl();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC  );
        curl_setopt($ch, CURLOPT_USERPWD, $webserviceUsername.":".$webservicePassword);
        curl_setopt($ch, CURLOPT_POST,count($request));
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($request));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $results = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpStatus != 200) {
            throw new \Magento\Framework\Exception\LocalizedException(__('HTTP Status code' . $httpStatus . " " . $webserviceUsername . ":" . $webservicePassword));
        }

        if ($results === false) {
            throw new \Magento\Framework\Exception\LocalizedException(__('HTTP Status code' . $results));
        }

        parse_str($results, $resultArr);

        curl_close($ch);

        // log the result
        $this->_adyenLogger->info('The response to adyen: ' . print_r($resultArr, true));

        return $resultArr;
    }

    public function authorise3d($payment)
    {
        $order = $payment->getOrder();
        $merchantAccount = $this->_adyenHelper->getAdyenAbstractConfigData("merchant_account");
        $shopperIp = $order->getRemoteIp();

        $md = $payment->getAdditionalInformation('md');
        $paResponse = $payment->getAdditionalInformation('paResponse');

        $request = array(
            "action" => "Payment.authorise3d",
            "paymentRequest3d.merchantAccount" => $merchantAccount,
            "paymentRequest3d.browserInfo.userAgent" => $_SERVER['HTTP_USER_AGENT'],
            "paymentRequest3d.browserInfo.acceptHeader" => $_SERVER['HTTP_ACCEPT'],
            "paymentRequest3d.md" => $md,
            "paymentRequest3d.paResponse" => $paResponse,
            "paymentRequest3d.shopperIP" => $shopperIp
        );

        return $this->_apiRequest($request);
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
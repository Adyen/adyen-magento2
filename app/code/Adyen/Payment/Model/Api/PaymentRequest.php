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

class PaymentRequest extends \Magento\Framework\Object
{
    protected $_scopeConfig;
    protected $_code;
    protected $_logger;
    protected $_encryptor;
    protected $_adyenHelper;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Adyen\Payment\Helper\Data $adyenHelper,
        array $data = []
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_encryptor = $encryptor;
        $this->_code = "adyen_cc";
        $this->_adyenHelper = $adyenHelper;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $paymentMethodCode = "adyen_abstract", $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getStore();
        }

        // $this->_code to get current methodcode
        $path = 'payment/' . $paymentMethodCode . '/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function fullApiRequest($payment)
    {
        $order = $payment->getOrder();
        $amount = $order->getGrandTotal();
        $customerEmail = $order->getCustomerEmail();
        $shopperIp = $order->getRemoteIp();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $merchantAccount = $this->getConfigData("merchant_account");

        $this->_logger->critical("fullApiRequest1 ");

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

            $requestDelivery = array("paymentRequest.card.billingAddress.street" => $addressArray['name'],
                "paymentRequest.card.billingAddress.postalCode" => $deliveryAddress->getPostcode(),
                "paymentRequest.card.billingAddress.city" => $deliveryAddress->getCity(),
                "paymentRequest.card.billingAddress.houseNumberOrName" => $addressArray['house_number'],
                "paymentRequest.card.billingAddress.stateOrProvince" => $deliveryAddress->getRegionCode(),
                "paymentRequest.card.billingAddress.country" => $deliveryAddress->getCountryId()
            );
            $request = array_merge($request, $requestDelivery);
        }


        // TODO get CSE setting
        $cseEnabled = true;
        if($cseEnabled) {
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

        $this->_logger->critical("fullApiRequest");
        $this->_logger->critical(print_r($request, true));

        return $this->_apiRequest($request);
    }


    protected function _apiRequest($request)
    {

        // Use test or live credentials depends on demo mode
        if($this->_adyenHelper->isDemoMode()) {
            $webserviceUsername = $this->getConfigData("ws_username_test");
            $webservicePassword = $this->decryptPassword($this->getConfigData("ws_password_test"));
            $url = "https://pal-test.adyen.com/pal/adapter/httppost";
        } else {
            $webserviceUsername = $this->getConfigData("ws_username_live");
            $webservicePassword = $this->decryptPassword($this->getConfigData("ws_password_live"));
            $url = "https://pal-live.adyen.com/pal/adapter/httppost";
        }

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

        parse_str($results, $results);

        $this->_logger->critical("result is" . print_r($results,true));

        curl_close($ch);

        return $results;
    }

    public function authorise3d($payment)
    {
        $order = $payment->getOrder();
        $merchantAccount = $this->getConfigData("merchant_account");
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
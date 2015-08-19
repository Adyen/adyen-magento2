<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Adyen\Payment\Model\Api;

class PaymentRequest extends \Magento\Framework\Object
{
    protected $_scopeConfig;
    protected $_code;
    protected $_logger;
    protected $_encryptor;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        array $data = []
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_encryptor = $encryptor;
        $this->_code = "adyen_cc";
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getStore();
        }
        $path = 'payment/' . $this->_code . '/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function fullApiRequest($merchantAccount, $payment)
    {
        $order = $payment->getOrder();

        if($order) {
            $this->_logger->critical("TEST5!!:" . print_r($order->getIncrementId(), true));
        }


        $this->_logger->critical("CLASS OBJECT:" . get_class($payment));

        $request = array(
            "action" => "Payment.authorise",
            "paymentRequest.merchantAccount" => $merchantAccount,
            "paymentRequest.amount.currency" => "EUR",
            "paymentRequest.amount.value" => "199",
            "paymentRequest.reference" => "TEST-PAYMENT-" . date("Y-m-d-H:i:s"),
            "paymentRequest.shopperIP" => "ShopperIPAddress",
            "paymentRequest.shopperEmail" => "TheShopperEmailAddress",
            "paymentRequest.shopperReference" => "YourReference",
            "paymentRequest.fraudOffset" => "0",

            "paymentRequest.card.billingAddress.street" => "Simon Carmiggeltstraat",
            "paymentRequest.card.billingAddress.postalCode" => "1011 DJ",
            "paymentRequest.card.billingAddress.city" => "Amsterdam",
            "paymentRequest.card.billingAddress.houseNumberOrName" => "6-50",
            "paymentRequest.card.billingAddress.stateOrProvince" => "",
            "paymentRequest.card.billingAddress.country" => "NL",

            "paymentRequest.card.expiryMonth" => $payment->getCcExpMonth(),
            "paymentRequest.card.expiryYear" => $payment->getCcExpYear(),
            "paymentRequest.card.holderName" => $payment->getCcOwner(),
            "paymentRequest.card.number" => $payment->getCcNumber(),
            "paymentRequest.card.cvc" => $payment->getCcCid(),
        );

        $this->_logger->critical("fullApiRequest");
        $this->_logger->critical(print_r($request, true));

        return $this->_apiRequest($request);
    }


    protected function _apiRequest($request) {

        $webserviceUsername = $this->getConfigData("webservice_username");
        $webservicePassword = $this->decryptPassword($this->getConfigData("webservice_password")); // DECODE!!

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://pal-test.adyen.com/pal/adapter/httppost");
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

        throw new \Magento\Framework\Exception\LocalizedException(__('HTTP Status code' . print_r($results, true)));

        parse_str($results,$results);

        curl_close($ch);

        return $results;
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
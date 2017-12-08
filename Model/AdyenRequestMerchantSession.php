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
 * Adyen Payment Module
 *
 * Copyright (c) 2017 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\AdyenRequestMerchantSessionInterface;

class AdyenRequestMerchantSession implements AdyenRequestMerchantSessionInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * AdyenRequestMerchantSession constructor.
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_storeManager = $storeManager;
    }

    /**
     * {@inheritDoc}
     */
    public function getMerchantSession()
    {
//        $result = "{\"test\": \"test\"}";
//        return $result;
//        $params = $this->getRequest()->getParams();

//        $validationUrl = $params['validationURL'];
        // Works for test and live. Maybe we need to switch for validationUrl from callback event waiting for apple to respond
        $validationUrl = "https://apple-pay-gateway-cert.apple.com/paymentservices/startSession";

        // create a new cURL resource
        $ch = curl_init();

        $merchantIdentifier = $this->_adyenHelper->getAdyenApplePayMerchantIdentifier();

        $domainName = $_SERVER['SERVER_NAME'];
        $displayName = $this->_storeManager->getStore()->getName();

        $data = '{
            "merchantIdentifier":"' . $merchantIdentifier . '",
            "domainName":"' . $domainName . '",
            "displayName":"' . $displayName . '"
        }';

        curl_setopt($ch, CURLOPT_URL, $validationUrl);

        // location applepay certificates
        $fullPathLocationPEMFile = $this->_adyenHelper->getAdyenApplePayPemFileLocation();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSLCERT, $fullPathLocationPEMFile);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            )
        );

        $result = curl_exec($ch);

        curl_close($ch);
        return $result;

//        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        $errno = curl_errno($ch);
//        $message = curl_error($ch);
//
//        if ($httpStatus != 200 && $result) {
////            Mage::log("Check if your PEM file location is correct location is now defined:" . $fullPathLocationPEMFile,
////                Zend_Log::ERR, 'adyen_exception.log');
////            Mage::log("Apple Merchant Valdiation Failed. Please check merchantIdentifier, domainname and PEM file. Request is: " . var_export($data,
////                    true) . "RESULT:" . $result . " HTTPS STATUS:" . $httpStatus . "VALIDATION URL:" . $validationUrl,
////                Zend_Log::ERR, 'adyen_exception.log');
//        } elseif (!$result) {
//            $errno = curl_errno($ch);
//            $message = curl_error($ch);
//
//            curl_close($ch);
//
//            $msg = "\n(Network error [errno $errno]: $message)";
////            Mage::log($msg, Zend_Log::ERR, 'adyen_exception.log');
////            throw new \Exception($msg);
//        }
//
//        // close cURL resource, and free up system resources
//        curl_close($ch);
//        return $result;

    }

//    public function isJson($string) {
//        json_decode($string);
//        return (json_last_error() == JSON_ERROR_NONE);
//    }
}
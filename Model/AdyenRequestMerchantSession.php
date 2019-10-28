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
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

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
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->_storeManager = $storeManager;
    }


    /**
     * Get the merchant Session from Apple to start Apple Pay transaction
     *
     * @return mixed
     */
    public function getMerchantSession()
    {
        // Works for test and live. Maybe we need to switch for validationUrl from callback event waiting for apple to respond
        $validationUrl = "https://apple-pay-gateway-cert.apple.com/paymentservices/startSession";

        // create a new cURL resource
        $ch = curl_init();

        $merchantIdentifier = $this->_adyenHelper->getAdyenApplePayMerchantIdentifier();
        $domainName = parse_url($this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB))['host'];
        $displayName = $this->_storeManager->getStore()->getName();

        $data = '{
            "merchantIdentifier":"' . $merchantIdentifier . '",
            "domainName":"' . $domainName . '",
            "displayName":"' . $displayName . '"
        }';

        $this->_adyenLogger->addAdyenDebug("JSON Request is: " . print_r($data, true));

        curl_setopt($ch, CURLOPT_URL, $validationUrl);

        // location applepay certificates
        $fullPathLocationPEMFile = $this->_adyenHelper->getAdyenApplePayPemFileLocation();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSLCERT, $fullPathLocationPEMFile);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            ]);

        $result = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // log the raw response
        $this->_adyenLogger->addAdyenDebug("JSON Response is: " . $result);

        // result not 200 throw error
        if ($httpStatus != 200 && $result) {
            $this->_adyenLogger->addAdyenDebug("Error Apple, API HTTP Status is: " . $httpStatus . " result is:" . $result);
        } elseif (!$result) {
            $errno = curl_errno($ch);
            $message = curl_error($ch);
            $msg = "(Network error [errno $errno]: $message)";
            $this->_adyenLogger->addAdyenDebug($msg);
        }

        curl_close($ch);
        return $result;
    }
}

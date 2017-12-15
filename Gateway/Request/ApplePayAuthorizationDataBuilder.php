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


namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Setup\Exception;

class ApplePayAuthorizationDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $_adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $_adyenLogger;

    /**
     * CaptureDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
    }

    public function build(array $buildSubject)
    {
        $request = [];
        $parsedToken = [];

        // TODO: Implement build() method.
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $paymentDataObject->getOrder();
        $storeId = $order->getStoreId();

        $token = $payment->getAdditionalInformation('token');

        $this->_adyenLogger->addAdyenDebug("\ntoken is " . $token);
        // could be that token is json string need to parse it to array

        // get payment data
        if ($token) {
            $parsedToken = json_decode($token);
            $this->_adyenLogger->addAdyenDebug("\n\n Parsed token is " . print_r($parsedToken, true));
            $paymentData = $parsedToken->token->paymentData;
            $this->_adyenLogger->addAdyenDebug("\n\n payment data is: " . print_r($paymentData, true));
            try {
                $paymentData = base64_encode(json_encode($paymentData));
                $request['additionalData']['payment.token'] = $paymentData;
            } catch (\Exception $exception) {
                $this->_adyenLogger->addAdyenDebug("exception thrown");
                $this->_adyenLogger->addAdyenDebug("exception: " . $exception->getMessage());
                $this->_adyenLogger->addAdyenDebug("exception done");
            }

            $this->_adyenLogger->addAdyenDebug("\n\n request data is: " . print_r($request, true));
        }
        return $request;
    }

}
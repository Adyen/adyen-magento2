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
        $requestBody = [];
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $token = $payment->getAdditionalInformation('token');

        $requestBody['paymentMethod']['type'] = 'applepay';

        // get payment data
        if ($token) {
            $parsedToken = json_decode($token);
            $paymentData = $parsedToken->token->paymentData;
            try {
                $paymentData = base64_encode(json_encode($paymentData));
                $requestBody['paymentMethod']['applepay.token'] = $paymentData;
            } catch (\Exception $exception) {
                $this->_adyenLogger->addAdyenDebug("exception: " . $exception->getMessage());
            }
        } else {
            $this->_adyenLogger->addAdyenDebug("PaymentToken is empty");
        }

        $request['body'] = $requestBody;

        return $request;
    }
}

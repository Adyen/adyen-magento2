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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */


namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Setup\Exception;

class GooglePayAuthorizationDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * GooglePayAuthorizationDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
    }

    public function build(array $buildSubject)
    {
        $request = [];
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $token = $payment->getAdditionalInformation('token');

        $request['paymentMethod']['type'] = 'paywithgoogle';

        // get payment data
        if ($token) {
            $parsedToken = json_decode($token);
            $paymentData = $parsedToken->token->paymentData;
            try {
                $paymentData = base64_encode(json_encode($paymentData));
				$request['paymentMethod']['paywithgoogle.token'] = $paymentData;
            } catch (\Exception $exception) {
                $this->adyenLogger->addAdyenDebug("exception: " . $exception->getMessage());
            }
        } else {
            $this->adyenLogger->addAdyenDebug("PaymentToken is empty");
        }

        return $request;
    }
}

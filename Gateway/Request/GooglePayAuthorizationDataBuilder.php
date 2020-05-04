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
        $requestBody = [];
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $token = $payment->getAdditionalInformation('token');

        $requestBody['paymentMethod']['type'] = 'paywithgoogle';
        // get payment data
        if ($token) {
            $parsedToken = json_decode($token);
            try {
                $requestBody['paymentMethod']['paywithgoogle.token'] = $parsedToken;
            } catch (\Exception $exception) {
                $this->adyenLogger->addAdyenDebug("exception: " . $exception->getMessage());
            }
        } else {
            $this->adyenLogger->addAdyenDebug("PaymentToken is empty");
        }

        $request['body'] = $requestBody;

        return $request;
    }
}

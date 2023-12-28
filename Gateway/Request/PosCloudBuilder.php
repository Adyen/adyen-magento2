<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\PaymentMethods;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PosCloudBuilder implements BuilderInterface
{
    /**
     * In case of older implementation (using AdyenInitiateTerminalApi::initiate) initiate call was already done so we pass its result.
     * Otherwise, we will do the initiate call here, using initiatePosPayment() so we pass parameters required for the initiate call
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDataObject->getPayment();
        $chainCalls = $payment->getAdditionalInformation('chain_calls');

        if ($chainCalls) {
            $body = [
                'terminalID' => $payment->getAdditionalInformation('terminal_id'),
                'numberOfInstallments' => $payment->getAdditionalInformation('number_of_installments'),
                'chainCalls' => $payment->getAdditionalInformation('chain_calls'),
                'fundingSource' => $payment->getAdditionalInformation('funding_source') ?? PaymentMethods::FUNDING_SOURCE_CREDIT,
                'order' => $payment->getOrder()
            ];
        } else {
            $body = [
                "response" => $payment->getAdditionalInformation("terminalResponse"),
                "serviceID" => $payment->getAdditionalInformation("serviceID"),
                "initiateDate" => $payment->getAdditionalInformation("initiateDate"),
                "terminalID" => $payment->getAdditionalInformation("terminal_id"),
                'fundingSource' => $payment->getAdditionalInformation('funding_source')
            ];
        }

        $request['body'] = $body;

        return $request;
    }
}

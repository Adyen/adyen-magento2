<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundMotoMerchantAccountDataBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
//        $merchantAccount = $paymentDataObject->getPayment()->getAdditionalInformation('motoMerchantAccount');


        $request['body'] = [];
        $request['clientConfig']['motoMerchantAccount'] = $payment->getAdditionalInformation('motoMerchantAccount');
        $request['clientConfig']['storeId'] = $payment->getMethodInstance()->getStore();
        $request['clientConfig']['isMotoTransaction'] = true;
//        $requestBody[] = ["merchantAccount" => $merchantAccount];z
        return $request;
    }
}

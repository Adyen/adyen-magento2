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
        $merchantAccount = $paymentDataObject->getPayment()->getAdditionalInformation('motoMerchantAccount');

        $requestBody[] = ["merchantAccount" => $merchantAccount];
        $request['clientConfig']['isMotoTransaction'] = true;
        $request['body'] = $requestBody;
        return $request;
    }
}

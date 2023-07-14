<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class RefundMotoMerchantAccountDataBuilder
 */
class RefundMotoMerchantAccountDataBuilder implements BuilderInterface
{
    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $merchantAccount = $paymentDataObject->getPayment()->getAdditionalInformation('motoMerchantAccount');

        $requestBody[] = ["merchantAccount" => $merchantAccount];

        $request['body'] = $requestBody;
        return $request;
    }
}

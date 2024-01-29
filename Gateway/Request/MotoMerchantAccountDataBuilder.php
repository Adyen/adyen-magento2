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

use Adyen\AdyenException;
use Adyen\Payment\Helper\Requests;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class MotoMerchantAccountDataBuilder implements BuilderInterface
{
    /**
     * @throws AdyenException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();

        $motoMerchantAccount = $payment->getAdditionalInformation('motoMerchantAccount');

        if (is_null($motoMerchantAccount)) {
            throw new AdyenException('MOTO merchant account was not set in payment information!');
        }

        $request['body'] = [
            Requests::MERCHANT_ACCOUNT => $motoMerchantAccount
        ];

        return $request;
    }
}

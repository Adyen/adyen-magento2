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
use Magento\Payment\Gateway\Request\BuilderInterface;

class MotoMerchantAccountDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Requests
     */
    private $adyenRequestsHelper;

    /**
     * MerchantAccountDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Requests $adyenRequestsHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Requests $adyenRequestsHelper
    ) {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws AdyenException
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();

        $motoMerchantAccount = $payment->getAdditionalInformation('motoMerchantAccount');

        if (is_null($motoMerchantAccount)) {
            throw new AdyenException('MOTO merchant account was not set in payment information!');
        }

        $request['body'] = $this->adyenRequestsHelper->buildMotoMerchantAccountData($motoMerchantAccount);
        $request['clientConfig']['storeId'] = $payment->getMethodInstance()->getStore();

        return $request;
    }
}

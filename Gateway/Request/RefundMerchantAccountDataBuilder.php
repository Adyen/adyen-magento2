<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class RefundMerchantAccountDataBuilder
 */
class RefundMerchantAccountDataBuilder implements BuilderInterface
{
    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $storeId = $paymentDataObject->getOrder()->getStoreId();
        $method = $payment->getMethod();
        $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($method, $storeId);

        $requestBody[] = ["merchantAccount" => $merchantAccount];

        $request['body'] = $requestBody;
        return $request;
    }
}

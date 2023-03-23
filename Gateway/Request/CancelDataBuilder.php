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

use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class CustomerDataBuilder
 */
class CancelDataBuilder implements BuilderInterface
{
    /**
     * Create cancel_or_refund request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $order = $paymentDataObject->getOrder();
        $payment = $paymentDataObject->getPayment();
        $pspReference = $payment->getCcTransId();

        $request['body'] = [
            "reference" => $order->getOrderIncrementId(),
            "originalReference" => $pspReference
        ];

        $request['clientConfig'] = ["storeId" => $order->getStoreId()];
        return $request;
    }
}

<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class DeviceFingerprintDataBuilder  implements BuilderInterface
{
    /**
     * @param array $buildSubject
     * @return array[]
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();

        if ($payment->getAdditionalInformation(AdyenCcDataAssignObserver::DEVICE_FINGERPRINT)) {
            $requestBody['deviceFingerprint'] = $payment->getAdditionalInformation(
                AdyenCcDataAssignObserver::DEVICE_FINGERPRINT
            );
        }

        return [
            'body' => $requestBody ?? []
        ];
    }
}

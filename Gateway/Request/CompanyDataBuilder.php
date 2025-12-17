<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class CompanyDataBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        /** @var Order $order */
        $order = $payment->getOrder();
        $billingAddress = $order->getBillingAddress();
        $company = [];

        if (!empty($billingAddress->getVatId())) {
            $company['taxId'] = $billingAddress->getVatId();
        }

        if (!empty($billingAddress->getCompany())) {
            $company['name'] = $billingAddress->getCompany();
        }

        return [
            'body' => [
                'company' => $company
            ]
        ];
    }
}

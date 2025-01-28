<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen MV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request\Header;

use Adyen\Payment\Helper\Data;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class HeaderDataBuilder implements BuilderInterface, HeaderDataBuilderInterface
{
    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * PaymentDataBuilder constructor.
     *
     * @param Data $adyenHelper
     */
    public function __construct(
        Data $adyenHelper
    )
    {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $headers = $this->adyenHelper->buildRequestHeaders($payment);
        return ['headers' => $headers];
    }
}

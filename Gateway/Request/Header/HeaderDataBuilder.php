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
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class HeaderDataBuilder implements HeaderDataBuilderInterface, BuilderInterface
{
//    /**
//     * @var ProductMetadataInterface
//     */
//    protected ProductMetadataInterface $productMetadata;

    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * PaymentDataBuilder constructor.
     *
     * @param Data $adyenHelper
     */
    public function __construct( Data $adyenHelper )
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
        $headers = $this->buildRequestHeaders($payment);
        return ['headers' => $headers];
    }

    public function buildRequestHeaders($payment = null): array
    {
        $magentoDetails = $this->adyenHelper->getMagentoDetails();
        $headers = [
            HeaderDataBuilderInterface::EXTERNAL_PLATFORM_NAME => $magentoDetails['name'],
            HeaderDataBuilderInterface::EXTERNAL_PLATFORM_VERSION => $magentoDetails['version'],
            HeaderDataBuilderInterface::EXTERNAL_PLATFORM_EDITION => $magentoDetails['edition'],
            HeaderDataBuilderInterface::MERCHANT_APPLICATION_NAME => $this->adyenHelper->getModuleName(),
            HeaderDataBuilderInterface::MERCHANT_APPLICATION_VERSION => $this->adyenHelper->getModuleVersion()
        ];

        if(isset($payment) && !is_null($payment->getAdditionalInformation(HeaderDataBuilderInterface::FRONTEND_TYPE))) {
            $headers[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] =
                $payment->getAdditionalInformation(HeaderDataBuilderInterface::FRONTEND_TYPE);
        }

        return $headers;
    }
}

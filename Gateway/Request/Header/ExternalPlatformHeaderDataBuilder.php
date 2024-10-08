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

class ExternalPlatformHeaderDataBuilder implements ExternalPlatformHeaderDataBuilderInterface
{
    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $productMetadata;

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

    public function getMagentoDetails(): array
    {
        return [
            'name' => $this->productMetadata->getName(),
            'version' => $this->productMetadata->getVersion(),
            'edition' => $this->productMetadata->getEdition(),
        ];
    }

    public function buildRequestHeaders($payment = null): array
    {
        $magentoDetails = $this->getMagentoDetails();
        $headers = [
            ExternalPlatformHeaderDataBuilderInterface::EXTERNAL_PLATFORM_NAME => $magentoDetails['name'],
            ExternalPlatformHeaderDataBuilderInterface::EXTERNAL_PLATFORM_VERSION => $magentoDetails['version'],
            ExternalPlatformHeaderDataBuilderInterface::EXTERNAL_PLATFORM_EDITION => $magentoDetails['edition'],
            ExternalPlatformHeaderDataBuilderInterface::MERCHANT_APPLICATION_NAME => $this->adyenHelper->getModuleName(),
            ExternalPlatformHeaderDataBuilderInterface::MERCHANT_APPLICATION_VERSION => $this->adyenHelper->getModuleVersion()
        ];

        if(isset($payment) && !is_null($payment->getAdditionalInformation(ExternalPlatformHeaderDataBuilderInterface::FRONTEND_TYPE))) {
            $headers[ExternalPlatformHeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] =
                $payment->getAdditionalInformation(ExternalPlatformHeaderDataBuilderInterface::FRONTEND_TYPE);
        }

        return $headers;
    }
}

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



namespace Adyen\Payment\Gateway\Request;

use Adyen\Exception\MissingDataException;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Requests;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class HeaderDataBuilder implements BuilderInterface
{
    /**
     * @var Requests
     */
    private $adyenRequestsHelper;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * PaymentDataBuilder constructor.
     *
     * @param Requests $adyenRequestsHelper
     * @param ChargedCurrency $chargedCurrency
     */
    public function __construct(
        Requests        $adyenRequestsHelper,
        ChargedCurrency $chargedCurrency
    )
    {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws MissingDataException
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDataObject->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();

        $frontendType = $additionalInformation['frontendType'] ?? 'headless'; //If not set we assume headless

        $request['headers'] = [
            'Content-Type' => 'application/json',
            'External-Platform-FrontendType' => $frontendType  // Add frontend type to the headers
        ];

        return $request;
    }
}

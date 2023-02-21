<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RecurringVaultDataBuilder implements BuilderInterface
{
    private StateData $stateData;

    public function __construct(StateData $stateData)
    {
        $this->stateData = $stateData;
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
        $order = $paymentDataObject->getOrder();
        $extensionAttributes = $payment->getExtensionAttributes();
        $paymentToken = $extensionAttributes->getVaultPaymentToken();
        $details = json_decode((string) ($paymentToken->getTokenDetails() ?: '{}'), true);

        // Initialize the request body with the current state data
        $requestBody = $this->stateData->getStateData($order->getQuoteId());

        // For now this will only be used by tokens created trough adyen_hpp payment methods
        if (array_key_exists(Vault::TOKEN_TYPE, $details)) {
            $requestBody['recurringProcessingModel'] = $details[Vault::TOKEN_TYPE];
        }

        $request['body'] = $requestBody;

        return $request;
    }
}

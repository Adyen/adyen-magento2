<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\Vault;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RecurringVaultDataBuilder implements BuilderInterface
{
    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $requestBody = [];
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $extensionAttributes = $payment->getExtensionAttributes();
        $paymentToken = $extensionAttributes->getVaultPaymentToken();

        // Add paymentMethod object in array, since currently checkout component is not loaded for vault payment methods
        $requestBody['paymentMethod'] = [
            'storedPaymentMethodId' => $paymentToken->getGatewayToken()
        ];

        // TODO: How are we going to decide what model to send when processing payment on the already existing token
        $requestBody['recurringProcessingModel'] = 'Subscription';

        $request['body'] = $requestBody;

        return $request;
    }
}

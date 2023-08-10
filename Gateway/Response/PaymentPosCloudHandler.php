<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Order\Payment;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentPosCloudHandler implements HandlerInterface
{
    private AdyenLogger $adyenLogger;
    private Vault $vaultHelper;

    public function __construct(
        AdyenLogger $adyenLogger,
        Vault $vaultHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->vaultHelper = $vaultHelper;
    }

    public function handle(array $handlingSubject, array $response)
    {
        $paymentResponse = $response['SaleToPOIResponse']['PaymentResponse'];
        $paymentDataObject = SubjectReader::readPayment($handlingSubject);

        $payment = $paymentDataObject->getPayment();

        // set transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);

        // do not send order confirmation mail
        $payment->getOrder()->setCanSendNewEmailFlag(false);



        if (!empty($paymentResponse['Response']['AdditionalResponse'])
        ) {
//            $this->vaultHelper->handlePaymentResponseRecurringDetails($this->payment,  )
            $pairs = \explode('&', (string) $paymentResponse['Response']['AdditionalResponse']);

            foreach ($pairs as $pair) {
                $nv = \explode('=', $pair);

                if ($nv[0] == 'recurring.recurringDetailReference') {
                    $recurringDetailReference = $nv[1];
                    break;
                }
            }

            if (!empty($recurringDetailReference) &&
                !empty($paymentResponse['PaymentResult']['PaymentInstrumentData']['CardData'])
            ) {
                $maskedPan = $paymentResponse['PaymentResult']['PaymentInstrumentData']['CardData']['MaskedPan'];
                $expiryDate = $paymentResponse['PaymentResult']['PaymentInstrumentData']['CardData']
                ['SensitiveCardData']['ExpiryDate']; // 1225
                $expiryDate = \substr((string) $expiryDate, 0, 2) . '/' . \substr((string) $expiryDate, 2, 2);
                $brand = $paymentResponse['PaymentResult']['PaymentInstrumentData']['CardData']['PaymentBrand'];

                // create additionalData so we can use the helper
                $additionalData = [];
                $additionalData['recurring.recurringDetailReference'] = $recurringDetailReference;
                $additionalData['cardBin'] = $recurringDetailReference;
                $additionalData['cardHolderName'] = '';
                $additionalData['cardSummary'] = \substr((string) $maskedPan, -4);
                $additionalData['expiryDate'] = $expiryDate;
                $additionalData['paymentMethod'] = $brand;
                $additionalData['recurring.recurringDetailReference'] = $recurringDetailReference;
                $additionalData['pos_payment'] = true;
            }
        }

        // set transaction(status)
        if (!empty($paymentResponse['PaymentResult']['PaymentAcquirerData']['AcquirerTransactionID']['TransactionID']))
        {
            $pspReference = $paymentResponse['PaymentResult']['PaymentAcquirerData']
            ['AcquirerTransactionID']['TransactionID'];
            $payment->setTransactionId($pspReference);
            // set transaction(payment)
        } else {
            $this->adyenLogger->error("Missing POS Transaction ID");
            throw new AdyenException("Missing POS Transaction ID");
        }

        // do not close transaction so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}

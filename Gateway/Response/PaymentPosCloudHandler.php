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

        if (!empty($paymentResponse) && isset($paymentResponse['Response']['Result'])) {
            $payment->setAdditionalInformation('resultCode', $paymentResponse['Response']['Result']);
        }

        if (!empty($paymentResponse['Response']['AdditionalResponse']))
        {
            $paymentResponseDecoded = json_decode(
                base64_decode($paymentResponse['Response']['AdditionalResponse']),
                true
            );
            $payment->setAdditionalInformation('additionalData', $paymentResponseDecoded['additionalData']);

            if (isset($paymentResponseDecoded['additionalData']['pspReference'])) {
                $payment->setAdditionalInformation('pspReference', $paymentResponseDecoded['additionalData']['pspReference']);
            }

            $this->vaultHelper->handlePaymentResponseRecurringDetails(
                $payment->getOrder()->getPayment(),
                $paymentResponseDecoded
            );

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

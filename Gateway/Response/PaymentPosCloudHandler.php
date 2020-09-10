<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentPosCloudHandler implements HandlerInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * PaymentPosCloudHandler constructor.
     *
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $paymentResponse
     * @return void
     * @throws \Exception
     */
    public function handle(array $handlingSubject, array $paymentResponse)
    {
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDataObject->getPayment();

        // set transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);

        // do not send order confirmation mail
        $payment->getOrder()->setCanSendNewEmailFlag(false);

        if (!empty($paymentResponse['Response']['AdditionalResponse'])
        ) {
            $pairs = explode('&', $paymentResponse['Response']['AdditionalResponse']);

            foreach ($pairs as $pair) {
                $nv = explode('=', $pair);

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
                $expiryDate = substr($expiryDate, 0, 2) . '/' . substr($expiryDate, 2, 2);
                $brand = $paymentResponse['PaymentResult']['PaymentInstrumentData']['CardData']['PaymentBrand'];

                // create additionalData so we can use the helper
                $additionalData = [];
                $additionalData['recurring.recurringDetailReference'] = $recurringDetailReference;
                $additionalData['cardBin'] = $recurringDetailReference;
                $additionalData['cardHolderName'] = '';
                $additionalData['cardSummary'] = substr($maskedPan, -4);
                $additionalData['expiryDate'] = $expiryDate;
                $additionalData['paymentMethod'] = $brand;
                $additionalData['recurring.recurringDetailReference'] = $recurringDetailReference;
                $additionalData['pos_payment'] = true;

                if (!$this->adyenHelper->isCreditCardVaultEnabled()) {
                    $this->adyenHelper->createAdyenBillingAgreement($payment->getOrder(), $additionalData);
                }
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
            throw new \Exception("Missing POS Transaction ID");
        }

        // do not close transaction so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}

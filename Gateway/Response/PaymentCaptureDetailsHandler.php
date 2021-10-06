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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentCaptureDetailsHandler implements HandlerInterface
{
    /** @var AdyenLogger $adyenLogger */
    private $adyenLogger;

    /**
     * PaymentCaptureDetailsHandler constructor.
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(AdyenLogger $adyenLogger)
    {
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var OrderPaymentInterface $payment */
        $payment = $payment->getPayment();

        if (array_key_exists(TransactionCapture::MULTIPLE_AUTHORIZATIONS, $response)) {
            $this->handleMultipleCaptureRequests($payment, $response);
        } else {
            // set pspReference as lastTransId only!
            $payment->setLastTransId($response['pspReference']);

            // The capture request will return a capture-received message, but it doesn't mean the capture has been final
            // so the invoice is set to Pending
            if ($response["response"] === TransactionCapture::CAPTURE_RECEIVED) {
                $this->setInvoiceToPending($payment);
            }
        }
    }

    /**
     * @param $payment
     * @param $responseContainer
     */
    public function handleMultipleCaptureRequests($payment, $responseContainer)
    {
        $this->adyenLogger->info(sprintf(
            'Handling multiple capture response in details handler for payment %s',
            $payment->getId()
        ));

        $captureNotReceived = [];

        foreach ($responseContainer[TransactionCapture::MULTIPLE_AUTHORIZATIONS] as $response) {
            if ($response["response"] !== TransactionCapture::CAPTURE_RECEIVED) {
                $captureNotReceived[] = $response['pspReference'];
            }
            $lastTransId = $response['pspReference'];
        }

        // Set transId to the last capture request
        $payment->setLastTransId($lastTransId);

        if (empty($captureNotReceived)) {
            $this->setInvoiceToPending($payment);
        } else {
            $this->adyenLogger->error(sprintf(
                'Response for transactions [%s] did not contain [capture-received]',
                implode(', ', $captureNotReceived)
            ));
        }
    }

    /**
     * Set payment to pending
     *
     * @param $payment
     * @return mixed
     */
    private function setInvoiceToPending($payment)
    {
        $payment->setIsTransactionPending(true);
        // Do not close parent authorisation since order can still be cancelled/refunded
        $payment->setShouldCloseParentTransaction(false);

        return $payment;
    }
}

<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class CheckoutPaymentsResponseHandler implements HandlerInterface
{
    public function __construct(
        protected readonly Data $adyenHelper
    ) { }

    /**
     * @param array $handlingSubject
     * @param array $responseCollection
     * @return void
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $responseCollection): void
    {
        // Always get the last item in the response collection and ignore the partial payments
        $response = end($responseCollection);

        $paymentDataObject = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDataObject->getPayment();

        $payment->getOrder()->setAdyenResulturlEventCode($response['resultCode']);
        $payment->setAdditionalInformation('resultCode', $response['resultCode']);
        $payment->setAdditionalInformation('3dActive', false);

        // Set the transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);
        // Do not close the transaction, so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        // Do not close the parent transaction
        $payment->setShouldCloseParentTransaction(false);

        if (!empty($response['pspReference'])) {
            $payment->setCcTransId($response['pspReference']);
            $payment->setLastTransId($response['pspReference']);
            $payment->setTransactionId($response['pspReference']);
            $payment->setAdditionalInformation('pspReference', $response['pspReference']);
        }

        if ($payment->getMethod() === PaymentMethods::ADYEN_CC &&
            !empty($response['additionalData']['paymentMethod']) &&
            $payment->getCcType() == null) {
            $payment->setCcType($response['additionalData']['paymentMethod']);
        }

        if (!empty($response['action'])) {
            $payment->setAdditionalInformation('action', $response['action']);
        }

        if (!empty($response['additionalData'])) {
            $payment->setAdditionalInformation('additionalData', $response['additionalData']);
        }

        if (!empty($response['details'])) {
            $payment->setAdditionalInformation('details', $response['details']);
        }

        if (!empty($response['donationToken'])) {
            $payment->setAdditionalInformation('donationToken', $response['donationToken']);
        }

        // Do not send order confirmation email for Boleto payments
        if ($payment->getMethod() != PaymentMethods::ADYEN_BOLETO) {
            $payment->getOrder()->setCanSendNewEmailFlag(false);
        }
    }
}

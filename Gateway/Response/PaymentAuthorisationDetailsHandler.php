<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class PaymentAuthorisationDetailsHandler implements HandlerInterface
{
    /**
     * @param array $handlingSubject
     * @param array $responseCollection
     */
    public function handle(array $handlingSubject, array $responseCollection): void
    {
        $payment = SubjectReader::readPayment($handlingSubject);

        /** @var OrderPaymentInterface $payment */
        $payment = $payment->getPayment();

        // set transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);

        // no not send order confirmation mail
        $payment->getOrder()->setCanSendNewEmailFlag(false);

        // for partial payments, non-giftcard payments will always be the last element in the collection
        // for non-partial, there is only one response in the collection
        $response = end($responseCollection);
        if (!empty($response['pspReference'])) {
            // set pspReference as transactionId
            $payment->setCcTransId($response['pspReference']);
            $payment->setLastTransId($response['pspReference']);

            // set transaction
            $payment->setTransactionId($response['pspReference']);
        }

        // do not close transaction so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}

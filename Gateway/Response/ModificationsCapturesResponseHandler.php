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

use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class ModificationsCapturesResponseHandler implements HandlerInterface
{
    /**
     * @param AdyenLogger $adyenLogger
     * @param Invoice $invoiceHelper
     */
    public function __construct(
        private readonly AdyenLogger $adyenLogger,
        private readonly Invoice $invoiceHelper
    ) { }

    /**
     * @param array $handlingSubject
     * @param array $responseCollection
     * @throws AlreadyExistsException
     */
    public function handle(array $handlingSubject, array $responseCollection): void
    {
        $payment = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $payment->getPayment();

        foreach ($responseCollection as $response) {
            if (count($responseCollection) > 1) {
                $this->adyenLogger->info(sprintf(
                    'Handling partial or multiple capture response for order %s',
                    $payment->getOrder()->getIncrementId()
                ));
            }

            $payment->setLastTransId($response['pspReference']);

            $this->invoiceHelper->createAdyenInvoice(
                $payment,
                $response['pspReference'] ?? '',
                $response[TransactionCapture::ORIGINAL_REFERENCE],
                $response[TransactionCapture::CAPTURE_AMOUNT][TransactionCapture::CAPTURE_VALUE]
            );
        }

        // Set the invoice status to pending and wait for CAPTURE webhook
        $payment->setIsTransactionPending(true);
        // Do not close parent authorisation since order can still be cancelled/refunded
        $payment->setShouldCloseParentTransaction(false);
    }
}

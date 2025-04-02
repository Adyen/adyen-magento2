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

use Adyen\Payment\Gateway\Http\Client\TransactionRefundInterface as TransactionRefund;
use Adyen\Payment\Helper\Creditmemo;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class ModificationsRefundsResponseHandler implements HandlerInterface
{
    /**
     * @param Creditmemo $creditmemoHelper
     * @param Data $adyenDataHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly Creditmemo $creditmemoHelper,
        private readonly Data $adyenDataHelper,
        private readonly AdyenLogger $adyenLogger
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
                    'Handling partial or multiple refund response for order %s',
                    $payment->getOrder()->getIncrementId()
                ));
            }

            $payment->setLastTransId($response[TransactionRefund::PSPREFERENCE]);

            $this->creditmemoHelper->createAdyenCreditMemo(
                $payment,
                $response[TransactionRefund::PSPREFERENCE],
                $response[TransactionRefund::ORIGINAL_REFERENCE],
                $this->adyenDataHelper->originalAmount(
                    $response[TransactionRefund::REFUND_AMOUNT],
                    $response[TransactionRefund::REFUND_CURRENCY]
                )
            );
        }

        /**
         * close current transaction because you have refunded the goods
         * but only on full refund close the authorisation
         */
        $payment->setIsTransactionClosed(true);
        $payment->setShouldCloseParentTransaction(!$payment->getCreditmemo()->getInvoice()->canRefund());
    }
}

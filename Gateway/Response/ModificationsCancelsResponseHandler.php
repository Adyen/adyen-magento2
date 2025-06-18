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

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class ModificationsCancelsResponseHandler implements HandlerInterface
{
    /**
     * @param array $handlingSubject
     * @param array $responseCollection
     * @return void
     */
    public function handle(array $handlingSubject, array $responseCollection): void
    {
        $payment = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $payment->getPayment();

        foreach ($responseCollection as $response) {
            $payment->setLastTransId($response['pspReference']);
        }

        // close transaction because you have cancelled the transaction
        $payment->setIsTransactionClosed(true);
        $payment->setShouldCloseParentTransaction(true);
    }
}

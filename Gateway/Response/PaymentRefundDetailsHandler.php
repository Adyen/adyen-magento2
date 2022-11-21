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

use Adyen\Payment\Gateway\Http\Client\TransactionRefund;
use Adyen\Payment\Helper\Creditmemo;
use Adyen\Payment\Helper\Data;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Adyen\Util\Currency;

class PaymentRefundDetailsHandler implements HandlerInterface
{
    /**
     * @var Creditmemo
     */
    private $creditmemoHelper;

    /** @var Data */
    private $adyenDataHelper;

    public function __construct(
        Creditmemo $creditmemoHelper,
        Data $adyenDataHelper
    ) {
        $this->creditmemoHelper = $creditmemoHelper;
        $this->adyenDataHelper = $adyenDataHelper;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @throws AlreadyExistsException|LocalizedException
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var Order\Payment $payment */
        $payment = $payment->getPayment();

        foreach ($response as $singleResponse) {
            if (isset($singleResponse['error'])) {
                throw new LocalizedException(
                    "The refund failed. Please make sure the amount is not greater than the limit or negative.
                    Otherwise, refer to the logs for details."
                );
            }

            // set pspReference as lastTransId only!
            $payment->setLastTransId($singleResponse['pspReference']);

            $currencyConverter = new Currency();

            $this->creditmemoHelper->createAdyenCreditMemo(
                $payment,
                $singleResponse['pspReference'],
                $singleResponse[TransactionRefund::ORIGINAL_REFERENCE],
                $this->adyenDataHelper->originalAmount(
                    $singleResponse[TransactionRefund::REFUND_AMOUNT],
                    $singleResponse[TransactionRefund::REFUND_CURRENCY]
                )
            );
        }

        /**
         * close current transaction because you have refunded the goods
         * but only on full refund close the authorisation
         */
        $payment->setIsTransactionClosed(true);
        $closeParent = !(bool)$payment->getCreditmemo()->getInvoice()->canRefund();
        $payment->setShouldCloseParentTransaction($closeParent);
    }
}

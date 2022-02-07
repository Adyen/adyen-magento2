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

use Adyen\Payment\Gateway\Http\Client\TransactionRefund;
use Adyen\Payment\Helper\Creditmemo;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Response\HandlerInterface;


class PaymentRefundDetailsHandler implements HandlerInterface
{
    /**
     * @var Creditmemo
     */
    private $creditmemoHelper;

    public function __construct(
        Creditmemo $creditmemoHelper
    )
    {
        $this->creditmemoHelper = $creditmemoHelper;
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
                throw new LocalizedException("The refund failed. Please make sure the amount is not greater than the limit or negative. Otherwise, refer to the logs for details.");
            }

            // set pspReference as lastTransId only!
            $payment->setLastTransId($singleResponse['pspReference']);

            $this->creditmemoHelper->createAdyenCreditmemo(
                $payment,
                $singleResponse['pspReference'],
                $singleResponse[TransactionRefund::ORIGINAL_REFERENCE],
                intval($singleResponse[TransactionRefund::REFUND_AMOUNT])
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

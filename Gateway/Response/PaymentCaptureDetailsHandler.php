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

use Magento\Framework\Webapi\Exception;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;

class PaymentCaptureDetailsHandler implements HandlerInterface
{
    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $payment */
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var OrderPaymentInterface $payment */
        $payment = $payment->getPayment();

        // set pspReference as lastTransId only!
        $psp = $response['pspReference'] ?? '';
        if (!$psp) {
            throw new Exception(__('PSP Reference is missing; response was %1', json_encode($response)));
        }
        $payment->setLastTransId($psp);

        /**
         * close current transaction because you have capture the goods
         * but do not close the authorisation becasue you can still cancel/refund order
         */
        $payment->setIsTransactionClosed(true);
        $payment->setShouldCloseParentTransaction(false);
    }
}

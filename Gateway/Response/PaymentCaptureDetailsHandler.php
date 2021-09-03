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

use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentCaptureDetailsHandler implements HandlerInterface
{
    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var OrderPaymentInterface $payment */
        $payment = $payment->getPayment();

        // set pspReference as lastTransId only!
        $payment->setLastTransId($response['pspReference']);

        // The capture request will return a capture-received message, but it doesn't mean the capture has been final
        // so the invoice is set to Pending
        if($response["response"]=='[capture-received]'){
            $payment->setIsTransactionPending(true);
        
        /**
         * close current transaction because you have capture the goods
         * but do not close the authorisation becasue you can still cancel/refund order
         */
        $payment->setIsTransactionClosed(true);
        $payment->setShouldCloseParentTransaction(false);
        }
    }
}

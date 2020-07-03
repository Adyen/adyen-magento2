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

class PaymentCommentHistoryRefundHandler implements HandlerInterface
{
    /**
     * @param array $handlingSubject
     * @param array $response
     * @return $this
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var OrderPaymentInterface $payment */
        $payment = $payment->getPayment();

        foreach ($response as $singleResponse) {
            if (isset($singleResponse['resultCode'])) {
                $responseCode = $singleResponse['resultCode'];
            } else {
                // try to get response from response key (used for modifications
                if (isset($singleResponse['response'])) {
                    $responseCode = $singleResponse['response'];
                } else {
                    $responseCode = "";
                }
            }

            if (isset($singleResponse['pspReference'])) {
                $pspReference = $singleResponse['pspReference'];
            } else {
                $pspReference = "";
            }

            $type = 'Adyen Result response:';
            $comment = __(
                '%1 <br /> authResult: %2 <br /> pspReference: %3 ',
                $type,
                $responseCode,
                $pspReference
            );

            if ($responseCode) {
                $payment->getOrder()->setAdyenResulturlEventCode($responseCode);
            }

            $payment->getOrder()->addStatusHistoryComment($comment);
        }

        return $this;
    }
}

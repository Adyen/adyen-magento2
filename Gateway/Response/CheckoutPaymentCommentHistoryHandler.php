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

class CheckoutPaymentCommentHistoryHandler implements HandlerInterface
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

        $comment = __("Adyen Result response:");

        if (isset($response['resultCode'])) {
            $responseCode = $response['resultCode'];
        } else {
            // try to get response from response key (used for modifications
            if (isset($response['response'])) {
                $responseCode = $response['response'];
            } else {
                $responseCode = "";
            }
        }

        if (isset($response['pspReference'])) {
            $pspReference = $response['pspReference'];
        } else {
            $pspReference = "";
        }

        if ($responseCode) {
            $comment .= '<br /> ' . __('authResult:') . ' ' . $responseCode;
            $payment->getOrder()->setAdyenResulturlEventCode($responseCode);
        }

        if ($pspReference) {
            $comment .= '<br /> ' . __('pspReference:') . ' ' . $pspReference;
        }

        $payment->getOrder()->addStatusHistoryComment($comment);

        return $this;
    }
}

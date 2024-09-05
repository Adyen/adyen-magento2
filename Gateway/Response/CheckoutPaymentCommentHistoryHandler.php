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

use Magento\Payment\Gateway\Response\HandlerInterface;

class CheckoutPaymentCommentHistoryHandler implements HandlerInterface
{
    /**
     * @param array $handlingSubject
     * @param array $responseCollection
     * @return $this
     */
    public function handle(array $handlingSubject, array $responseCollection)
    {
        $readPayment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);
        $payment = $readPayment->getPayment();
        $comment = __("Adyen Result response:");

        foreach ($responseCollection as $response) {
            $responseCode = $response['resultCode'] ?? $response['response'] ?? '';

            if (!empty($responseCode)) {
                $comment .= '<br /> ' . __('authResult:') . ' ' . $responseCode;
                $payment->getOrder()->setAdyenResulturlEventCode($responseCode);
            }

            if (isset($response['pspReference'])) {
                $comment .= '<br /> ' . __('pspReference:') . ' ' . $response['pspReference'];
            }
            $comment .= '<br /> ';
        }

        $payment->getOrder()->addStatusHistoryComment($comment, $payment->getOrder()->getStatus());
        return $this;
    }
}

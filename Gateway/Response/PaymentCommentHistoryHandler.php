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

use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentCommentHistoryHandler implements HandlerInterface
{
    /** @var AdyenLogger $adyenLogger */
    private $adyenLogger;

    /**
     * PaymentCaptureDetailsHandler constructor.
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(AdyenLogger $adyenLogger)
    {
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return $this
     */
    public function handle(array $handlingSubject, array $response)
    {
        $readPayment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        $payment = $readPayment->getPayment();

        if (array_key_exists(TransactionCapture::MULTIPLE_AUTHORIZATIONS, $response)) {
            return $this->handlePartialOrMultipleCaptureRequests($payment, $response);
        }

        $responseCode = $this->getResponseCode($response);
        $pspReference = $this->getPspReference($response);

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

        $payment->getOrder()->addStatusHistoryComment($comment, $payment->getOrder()->getStatus());

        return $this;
    }

    /**
     * Handle multiple capture requests by creating a comment for each request, and adding all the event codes
     * in the order model
     *
     * @param $payment
     * @param array $responseContainer
     * @return $this
     */
    private function handlePartialOrMultipleCaptureRequests($payment, array $responseContainer)
    {
        $this->adyenLogger->info(sprintf(
            'Handling partial OR multiple capture response in comment history handler for order %s',
            $payment->getOrder()->getIncrementId()
        ));

        $resultEventCodes = [];
        foreach ($responseContainer[TransactionCapture::MULTIPLE_AUTHORIZATIONS] as $response) {
            $responseCode = $this->getResponseCode($response);
            $pspReference = $this->getPspReference($response);
            $amount = $response[TransactionCapture::FORMATTED_CAPTURE_AMOUNT];

            $type = 'Adyen Result response:';
            $comment = __(
                '%1 <br /> authResult: %2 <br /> pspReference: %3 <br /> amount: %4 ',
                $type,
                $responseCode,
                $pspReference,
                $amount
            );

            $resultEventCodes[] = $responseCode;

            $payment->getOrder()->addStatusHistoryComment($comment, $payment->getOrder()->getStatus());
        }

        if (!empty($resultEventCodes)) {
            $payment->getOrder()->setAdyenResulturlEventCode(implode(', ', $resultEventCodes));
        }

        return $this;
    }

    /**
     * Search for the response code in the passed response array
     *
     * @param $response
     * @return string
     */
    private function getResponseCode($response)
    {
        if (isset($response['resultCode'])) {
            $responseCode = $response['resultCode'];
        } else {
            // try to get response from response key (used for modifications
            if (isset($response['response'])) {
                $responseCode = $response['response'];
            } else {
                $responseCode = '';
            }
        }

        return $responseCode;
    }

    /**
     * Get the pspReference or return empty string if not found
     *
     * @param $response
     * @return string
     */
    private function getPspReference($response)
    {
        if (isset($response['pspReference'])) {
            $pspReference = $response['pspReference'];
        } else {
            $pspReference = '';
        }

        return $pspReference;
    }
}

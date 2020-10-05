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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class PaymentResponseHandler
{
    const AUTHORISED = 'Authorised';
    const REFUSED = 'Refused';
    const REDIRECT_SHOPPER = 'RedirectShopper';
    const IDENTIFY_SHOPPER = 'IdentifyShopper';
    const CHALLENGE_SHOPPER = 'ChallengeShopper';
    const RECEIVED = 'Received';
    const PENDING = 'Pending';
    const PRESENT_TO_SHOPPER = 'PresentToShopper';
    const ERROR = 'Error';
    const CANCELLED = 'Cancelled';

    private $adyenLogger;

    public function __construct(
        AdyenLogger $adyenLogger
    ) {
        $this->adyenLogger = $adyenLogger;
    }

    public function formatPaymentResponse($resultCode, $action = null, $additionalData = null)
    {
        switch ($resultCode) {
            case self::AUTHORISED:
            case self::REFUSED:
            case self::ERROR:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode,
                ];
            case self::REDIRECT_SHOPPER:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
            case self::PENDING:
                return [
                    "isFinal" => false,
                    "resultCode" => $resultCode,
                    "action" => $action
                ];
            case self::PRESENT_TO_SHOPPER:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode,
                    "action" => $action
                ];
            case self::RECEIVED:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode,
                    "additionalData" => $additionalData
                ];
            default:
                return [
                    "isFinal" => true,
                    "resultCode" => self::ERROR,
                ];
        }
    }

    /**
     * @param $paymentsResponse
     * @param OrderPaymentInterface $payment
     * @param OrderInterface|null $order
     * @return bool
     */
    public function handlePaymentResponse($paymentsResponse, $payment, $order = null)
    {
        if (empty($paymentsResponse)) {
            $this->adyenLogger->error("Payment details call failed, paymentsResponse is empty");
            return false;
        }

        if (!empty($paymentsResponse['resultCode']))
        $payment->setAdditionalInformation('resultCode', $paymentsResponse['resultCode']);

        if (!empty($paymentsResponse['action'])) {
            $payment->setAdditionalInformation('action', $paymentsResponse['action']);
        }

        if (!empty($paymentsResponse['additionalData'])) {
            $payment->setAdditionalInformation('additionalData', $paymentsResponse['additionalData']);
        }

        if (!empty($paymentsResponse['pspReference'])) {
            $payment->setAdditionalInformation('pspReference', $paymentsResponse['pspReference']);
        }

        if (!empty($paymentsResponse['paymentData'])) {
            $payment->setAdditionalInformation('adyenPaymentData', $paymentsResponse['paymentData']);
        }

        switch ($paymentsResponse['resultCode']) {
            case self::PRESENT_TO_SHOPPER:
            case self::PENDING:
            case self::RECEIVED:

                break;
            //We don't need to handle these resultCodes
            case self::REDIRECT_SHOPPER:
                $this->adyenLogger->addAdyenResult("Customer was redirected.");
                if ($order) {
                    $order->addStatusHistoryComment(
                        __(
                            'Customer was redirected to an external payment page. (In case of card payments the shopper is redirected to the bank for 3D-secure validation.) Once the shopper is authenticated, 
                        the order status will be updated accordingly. 
                        <br />Make sure that your notifications are being processed! 
                        <br />If the order is stuck on this status, the shopper abandoned the session. 
                        The payment can be seen as unsuccessful. 
                        <br />The order can be automatically cancelled based on the OFFER_CLOSED notification. 
                        Please contact Adyen Support to enable this.'
                        )
                    )->save();
                }
                break;
            case self::AUTHORISED:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
                break;
            //These resultCodes cancel the order and log an error
            case self::REFUSED:
            case self::ERROR:
            default:
                if (!$order->canCancel()) {
                    $order->setState(Order::STATE_NEW);
                }
                //TODO check if order gets cancelled
                $order->cancel();
                $this->adyenLogger->error(
                    sprintf("Payment details call failed for action, resultCode is %s Raw API responds: %s",
                        $paymentsResponse['resultCode'],
                        print_r($paymentsResponse, true)
                    ));
                return false;
        }
        return true;
    }
}

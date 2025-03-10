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

namespace Adyen\Payment\Helper;

use Adyen\Payment\Api\Data\NotificationInterface;
use Adyen\Payment\Model\Notification;
use Magento\Sales\Api\Data\OrderInterface;

class OrderStatusHistory
{
    /**
     * @param ChargedCurrency $chargedCurrencyHelper
     * @param Data $adyenHelper
     */
    public function __construct(
        private readonly ChargedCurrency $chargedCurrencyHelper,
        private readonly Data $adyenHelper
    ) { }

    /**
     * Builds the order status history comment based on the Checkout API response
     *
     * @param array $response
     * @param string $actionName
     * @return string
     */
    public function buildApiResponseComment(array $response, string $actionName): string
    {
        $comment = '<strong>' . __("Adyen %1 response", $actionName) . '</strong><br />';

        if (isset($response['resultCode'])) {
            $comment .= __("Result code: %1", $response['resultCode']) . '<br />';
        }

        // Modification responses contain `status` but not `resultCode`.
        if (isset($response['status'])) {
            $comment .= __("Status: %1", $response['status']) . '<br />';
        }

        if (isset($response['pspReference'])) {
            $comment .= __("PSP reference: %1", $response['pspReference']) . '<br />';
        }

        if ($paymentMethod = $response['paymentMethod']['brand'] ?? $response['paymentMethod']['type'] ?? null) {
            $comment .= __("Payment method: %1", $paymentMethod) . '<br />';
        }

        if (isset($response['refusalReason'])) {
            $comment .= __("Refusal reason: %1", $response['refusalReason']) . '<br />';
        }

        if (isset($response['errorCode'])) {
            $comment .= __("Error code: %1", $response['errorCode']) . '<br />';
        }

        return $comment;
    }

    /**
     * Builds the order status history comment based on the webhook
     *
     * @param OrderInterface $order
     * @param NotificationInterface $notification
     * @param string|null $klarnaReservationNumber
     * @return string
     */
    public function buildWebhookComment(
        OrderInterface $order,
        NotificationInterface $notification,
        ?string $klarnaReservationNumber = null,
    ): string {
        $comment = '<strong>' . __(
            "Adyen %1%2 webhook",
            $this->getIsWebhookForPartialPayment($order, $notification) ? 'partial ': '',
            $notification->getEventCode()
        ) . '</strong><br />';

        if (!empty($notification->getPspreference())) {
            $comment .= __("PSP reference: %1", $notification->getPspreference()) . '<br />';
        }

        if (!empty($notification->getPaymentMethod())) {
            $comment .= __("Payment method: %1", $notification->getPaymentMethod()) . '<br />';
        }

        if (!empty($notification->getSuccess())) {
            $status = $notification->isSuccessful() ? 'Successful' : 'Failed';
            $comment .= __("Event status: %1", $status) . '<br />';
        }

        if (!empty($notification->getReason())) {
            $comment .= __("Reason: %1", $notification->getReason()) . '<br />';
        }

        if (isset($klarnaReservationNumber)) {
            $comment .= __("Reservation number: %1", $klarnaReservationNumber) . '<br />';
        }

        return $comment;
    }

    /**
     * Identifies whether if the notification belongs to a partial modification or not
     *
     * @param OrderInterface $order
     * @param NotificationInterface $notification
     * @return bool
     */
    private function getIsWebhookForPartialPayment(
        OrderInterface $order,
        NotificationInterface $notification
    ): bool {
        $isPartial = false;

        if (in_array($notification->getEventCode(), [Notification::REFUND, Notification::CAPTURE])) {
            // check if it is a full or partial refund
            $amount = $notification->getAmountValue();
            $orderAmountCurrency = $this->chargedCurrencyHelper->getOrderAmountCurrency($order, false);
            $formattedOrderAmount = $this->adyenHelper->formatAmount(
                $orderAmountCurrency->getAmount(),
                $orderAmountCurrency->getCurrencyCode()
            );

            if ($amount !== $formattedOrderAmount) {
                $isPartial = true;
            }
        }

        return $isPartial;
    }
}

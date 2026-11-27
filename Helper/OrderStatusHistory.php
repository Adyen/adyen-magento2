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
use Magento\Framework\Escaper;
use Magento\Sales\Api\Data\OrderInterface;

class OrderStatusHistory
{
    /**
     * @param ChargedCurrency $chargedCurrencyHelper
     * @param Data $adyenHelper
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly ChargedCurrency $chargedCurrencyHelper,
        private readonly Data $adyenHelper,
        private readonly Escaper $escaper
    ) { }

    /**
     * Builds the order status history comment based on the Checkout API response
     *
     * @param array $response
     * @param string $actionDescription
     * @param string $apiEndpoint
     * @return string
     */
    public function buildApiResponseComment(array $response, string $actionDescription, string $apiEndpoint): string
    {
        $comment = '<strong>' . __($actionDescription) . '</strong><br />';
        $comment .= __("API endpoint: %1", $apiEndpoint) . '<br />';

        if (isset($response['resultCode'])) {
            $comment .= __("Result code: %1", $this->escaper->escapeHtml($response['resultCode'])) . '<br />';
        }

        // Modification responses contain `status` but not `resultCode`.
        if (isset($response['status'])) {
            $comment .= __("Status: %1", $this->escaper->escapeHtml($response['status'])) . '<br />';
        }

        if (isset($response['pspReference'])) {
            $comment .= __("PSP reference: %1", $this->escaper->escapeHtml($response['pspReference'])) . '<br />';
        }

        if (isset($response['paymentPspReference'])) {
            $comment .= __(
                "Original PSP reference: %1",
                $this->escaper->escapeHtml($response['paymentPspReference'])
            ) . '<br />';
        }

        if ($paymentMethod = $response['paymentMethod']['brand'] ?? $response['paymentMethod']['type'] ?? null) {
            $comment .= __("Payment method: %1", $this->escaper->escapeHtml($paymentMethod)) . '<br />';
        }

        if (isset($response['refusalReason'])) {
            $comment .= __("Refusal reason: %1", $this->escaper->escapeHtml($response['refusalReason'])) . '<br />';
        }

        if (isset($response['errorCode'])) {
            $comment .= __("Error code: %1", $this->escaper->escapeHtml($response['errorCode'])) . '<br />';
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
            $this->escaper->escapeHtml(strtoupper((string) $notification->getEventCode()))
        ) . '</strong><br />';

        if (!empty($notification->getPspreference())) {
            $comment .= __(
                "PSP reference: %1",
                $this->escaper->escapeHtml($notification->getPspreference())
            ) . '<br />';
        }

        if (!empty($notification->getOriginalReference())) {
            $comment .= __(
                "Original PSP reference: %1",
                $this->escaper->escapeHtml($notification->getOriginalReference())
            ) . '<br />';
        }

        if (!empty($notification->getPaymentMethod())) {
            $comment .= __(
                "Payment method: %1",
                $this->escaper->escapeHtml($notification->getPaymentMethod())
            ) . '<br />';
        }

        if (!empty($notification->getFormattedAmountCurrency())) {
            $comment .= __(
                "Amount: %1",
                $this->escaper->escapeHtml($notification->getFormattedAmountCurrency())
            ) . '<br />';
        }

        if (!empty($notification->getSuccess())) {
            $status = $notification->isSuccessful() ? 'Successful' : 'Failed';
            $comment .= __("Event status: %1", $status) . '<br />';
        }

        if (!empty($notification->getReason())) {
            $comment .= __("Reason: %1", $this->escaper->escapeHtml($notification->getReason())) . '<br />';
        }

        if (isset($klarnaReservationNumber)) {
            $comment .= __(
                "Reservation number: %1",
                $this->escaper->escapeHtml($klarnaReservationNumber)
            ) . '<br />';
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

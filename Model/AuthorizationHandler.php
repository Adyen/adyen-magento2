<?php

namespace Adyen\Payment\Model;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;

readonly class AuthorizationHandler
{
    public function __construct(
        private AdyenOrderPayment $adyenOrderPaymentHelper,
        private CaseManagement $caseManagementHelper,
        private Invoice $invoiceHelper,
        private PaymentMethods $paymentMethodsHelper,
        private OrderHelper $orderHelper,
        private AdyenLogger $adyenLogger,
        private SerializerInterface $serializer
    ) { }

    /**
     * @param Order $order
     * @param string $paymentMethod
     * @param string $merchantReference
     * @param string $pspReference
     * @param int $amountValue
     * @param string $amountCurrency
     * @param Notification $notification
     * @return Order
     * @throws LocalizedException
     * @throws Exception
     */
    public function execute(
        Order $order,
        string $paymentMethod,
        string $merchantReference,
        string $pspReference,
        int $amountValue,
        string $amountCurrency,
        Notification $notification
    ): Order {
        $isAutoCapture = $this->paymentMethodsHelper->isAutoCapture($order, $paymentMethod);

        $this->adyenOrderPaymentHelper->createAdyenOrderPayment(
            $order,
            $isAutoCapture,
            $merchantReference,
            $pspReference,
            $paymentMethod,
            $amountValue,
            $amountCurrency
        );

        if ($this->adyenOrderPaymentHelper->isFullAmountAuthorized($order)) {
            $order = $this->orderHelper->setPrePaymentAuthorized($order);
            $this->orderHelper->updatePaymentDetails($order, $pspReference);

            $additionalData = $this->getAdditionalDataArray($notification);
            $requireFraudManualReview = $this->caseManagementHelper->requiresManualReview($additionalData);

            $order = $isAutoCapture
                ? $this->handleAutoCapture(
                    $order,
                    $pspReference,
                    $merchantReference,
                    $amountValue,
                    $requireFraudManualReview
                )
                : $this->handleManualCapture($order, $pspReference, $merchantReference, $requireFraudManualReview);

            $this->sendOrderMailIfNeeded($order, $paymentMethod);

            $payment = $order->getPayment();
            $payment->setAmountAuthorized($order->getGrandTotal());
            $payment->setBaseAmountAuthorized($order->getBaseGrandTotal());

            if ($isAutoCapture) {
                $order->setData('adyen_notification_payment_captured', 1);
            }
        }
        return $order;
    }

    /**
     * @param Order $order
     * @param string $pspReference
     * @param string $merchantReference
     * @param int $amountValue
     * @param bool $requireFraudManualReview
     * @return Order
     * @throws LocalizedException
     */
    private function handleAutoCapture(
        Order $order,
        string $pspReference,
        string $merchantReference,
        int $amountValue,
        bool $requireFraudManualReview
    ): Order
    {
        $this->invoiceHelper->createInvoice($order, true, $pspReference, $merchantReference, $amountValue);
        if ($requireFraudManualReview) {
            $order = $this->caseManagementHelper->markCaseAsPendingReview($order, $pspReference, true);
        } else {
            $order = $this->orderHelper->finalizeOrder($order, $pspReference, $merchantReference, $amountValue);
        }

        return $order;
    }

    /**
     * @param Order $order
     * @param string $pspReference
     * @param string $merchantReference
     * @param bool $requireFraudManualReview
     * @return Order
     */
    private function handleManualCapture(
        Order  $order,
        string $pspReference,
        string $merchantReference,
        bool   $requireFraudManualReview
    ): Order
    {
        if ($requireFraudManualReview) {
            $order = $this->caseManagementHelper->markCaseAsPendingReview($order, $pspReference);
        } else {
            $order->addCommentToStatusHistory(__('Capture Mode set to Manual'), $order->getStatus());
            $this->adyenLogger->addAdyenNotification(
                'Capture mode is set to Manual',
                [
                    'pspReference' => $pspReference,
                    'merchantReference' => $merchantReference
                ]
            );
        }

        return $order;
    }

    private function sendOrderMailIfNeeded(Order $order, string $paymentMethod): void
    {
        // For Boleto confirmation mail is sent on order creation
        // Send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
        if ($paymentMethod !== 'adyen_boleto' && !$order->getEmailSent()) {
            $this->orderHelper->sendOrderMail($order);
        }
    }

    private function getAdditionalDataArray(Notification $notification): array
    {
        $raw = $notification->getAdditionalData();
        return !empty($raw) ? (array) $this->serializer->unserialize($raw) : [];
    }
}

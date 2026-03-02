<?php

namespace Adyen\Payment\Model;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class AuthorizationHandler
{
    /**
     * @param AdyenOrderPayment $adyenOrderPaymentHelper
     * @param CaseManagement $caseManagementHelper
     * @param Invoice $invoiceHelper
     * @param PaymentMethods $paymentMethodsHelper
     * @param OrderHelper $orderHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly AdyenOrderPayment $adyenOrderPaymentHelper,
        private readonly CaseManagement $caseManagementHelper,
        private readonly Invoice $invoiceHelper,
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly OrderHelper $orderHelper,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param Order $order
     * @param string $paymentMethod
     * @param string $pspReference
     * @param int $amountValue
     * @param string $amountCurrency
     * @param array $additionalData
     * @return Order
     * @throws LocalizedException
     */
    public function execute(
        Order $order,
        string $paymentMethod,
        string $pspReference,
        int $amountValue,
        string $amountCurrency,
        array $additionalData
    ): Order {
        $isAutoCapture = $this->paymentMethodsHelper->isAutoCapture($order, $paymentMethod);

        $this->adyenOrderPaymentHelper->createAdyenOrderPayment(
            $order,
            $isAutoCapture,
            $pspReference,
            $paymentMethod,
            $amountValue,
            $amountCurrency
        );

        if ($this->adyenOrderPaymentHelper->isFullAmountAuthorized($order)) {
            $order = $this->orderHelper->setPrePaymentAuthorized($order);
            $this->orderHelper->updatePaymentDetails($order, $pspReference);

            $requireFraudManualReview = $this->caseManagementHelper->requiresManualReview($additionalData);

            $order = $isAutoCapture
                ? $this->handleAutoCapture(
                    $order,
                    $pspReference,
                    $amountValue,
                    $requireFraudManualReview
                )
                : $this->handleManualCapture($order, $pspReference, $requireFraudManualReview);

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
     * @param int $amountValue
     * @param bool $requireFraudManualReview
     * @return Order
     * @throws LocalizedException
     */
    private function handleAutoCapture(
        Order $order,
        string $pspReference,
        int $amountValue,
        bool $requireFraudManualReview
    ): Order {
        $this->invoiceHelper->createInvoice($order, true, $pspReference, $amountValue);
        if ($requireFraudManualReview) {
            $order = $this->caseManagementHelper->markCaseAsPendingReview($order, $pspReference, true);
        } else {
            $order = $this->orderHelper->finalizeOrder($order, $pspReference, $amountValue);
        }

        return $order;
    }

    /**
     * @param Order $order
     * @param string $pspReference
     * @param bool $requireFraudManualReview
     * @return Order
     */
    private function handleManualCapture(
        Order  $order,
        string $pspReference,
        bool   $requireFraudManualReview
    ): Order {
        if ($requireFraudManualReview) {
            $order = $this->caseManagementHelper->markCaseAsPendingReview($order, $pspReference);
        } else {
            $order->addCommentToStatusHistory(__('Capture Mode set to Manual'), $order->getStatus());
            $this->adyenLogger->addAdyenNotification(
                'Capture mode is set to Manual',
                [
                    'pspReference' => $pspReference,
                    'merchantReference' => $order->getIncrementId()
                ]
            );
        }

        return $order;
    }

    /**
     * @param Order $order
     * @param string $paymentMethod
     *
     * @return void
     */
    private function sendOrderMailIfNeeded(Order $order, string $paymentMethod): void
    {
        // For Boleto confirmation mail is sent on order creation
        // Send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
        if ($paymentMethod !== 'adyen_boleto' && !$order->getEmailSent()) {
            $this->orderHelper->sendOrderMail($order);
        }
    }
}

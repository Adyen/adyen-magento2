<?php

namespace Adyen\Payment\Model;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;

readonly class AuthorizationHandler
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
        private AdyenOrderPayment $adyenOrderPaymentHelper,
        private CaseManagement $caseManagementHelper,
        private Invoice $invoiceHelper,
        private PaymentMethods $paymentMethodsHelper,
        private OrderHelper $orderHelper,
        private AdyenLogger $adyenLogger
    ) { }

    /**
     * @param OrderInterface $order
     * @param string $paymentMethod
     * @param string $pspReference
     * @param int $amountValue
     * @param string $amountCurrency
     * @param array $additionalData
     * @return OrderInterface
     * @throws LocalizedException
     */
    public function execute(
        OrderInterface $order,
        string $paymentMethod,
        string $pspReference,
        int $amountValue,
        string $amountCurrency,
        array $additionalData
    ): OrderInterface {
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
     * @param OrderInterface $order
     * @param string $pspReference
     * @param int $amountValue
     * @param bool $requireFraudManualReview
     * @return OrderInterface
     * @throws LocalizedException
     */
    private function handleAutoCapture(
        OrderInterface $order,
        string $pspReference,
        int $amountValue,
        bool $requireFraudManualReview
    ): OrderInterface {
        $this->invoiceHelper->createInvoice($order, true, $pspReference, $amountValue);
        if ($requireFraudManualReview) {
            $order = $this->caseManagementHelper->markCaseAsPendingReview($order, $pspReference, true);
        } else {
            $order = $this->orderHelper->finalizeOrder($order, $pspReference, $amountValue);
        }

        return $order;
    }

    /**
     * @param OrderInterface $order
     * @param string $pspReference
     * @param bool $requireFraudManualReview
     * @return OrderInterface
     */
    private function handleManualCapture(
        OrderInterface  $order,
        string $pspReference,
        bool   $requireFraudManualReview
    ): OrderInterface {
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
     * @param OrderInterface $order
     * @param string $paymentMethod
     *
     * @return void
     */
    private function sendOrderMailIfNeeded(OrderInterface $order, string $paymentMethod): void
    {
        // For Boleto confirmation mail is sent on order creation
        // Send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
        if ($paymentMethod !== 'adyen_boleto' && !$order->getEmailSent()) {
            $this->orderHelper->sendOrderMail($order);
        }
    }
}

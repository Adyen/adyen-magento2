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

namespace Adyen\Payment\Gateway\Request;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data as DataHelper;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\Order\Payment as AdyenOrderPaymentResourceModel;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Invoice;

/**
 * Class CustomerDataBuilder
 */
class CaptureDataBuilder implements BuilderInterface
{
    /**
     * @param DataHelper $adyenHelper
     * @param ChargedCurrency $chargedCurrency
     * @param AdyenOrderPayment $adyenOrderPaymentHelper
     * @param AdyenLogger $adyenLogger
     * @param Context $context
     * @param AdyenOrderPaymentResourceModel $orderPaymentResourceModel
     * @param OpenInvoice $openInvoiceHelper
     * @param PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
        private readonly DataHelper $adyenHelper,
        private readonly ChargedCurrency $chargedCurrency,
        private readonly AdyenOrderPayment $adyenOrderPaymentHelper,
        private readonly AdyenLogger $adyenLogger,
        private readonly Context $context,
        private readonly AdyenOrderPaymentResourceModel $orderPaymentResourceModel,
        protected readonly OpenInvoice $openInvoiceHelper,
        private readonly PaymentMethods $paymentMethodsHelper
    ) { }

    /**
     * @throws AdyenException|LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();

        /** @var Invoice $latestInvoice */
        $latestInvoice = $order->getInvoiceCollection()->getLastItem();
        $invoiceAmountCurrency = $this->chargedCurrency->getInvoiceAmountCurrency($latestInvoice);

        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order);
        $currency = $invoiceAmountCurrency->getCurrencyCode();
        $amount = $this->adyenHelper->formatAmount($invoiceAmountCurrency->getAmount(), $currency);
        $orderAmountCents = $this->adyenHelper->formatAmount($orderAmountCurrency->getAmount(), $currency);

        $pspReference = $payment->getCcTransId();

        // If total amount has not been authorized
        if (!$this->adyenOrderPaymentHelper->isFullAmountAuthorized($order)) {
            $errorMessage = sprintf(
                'Unable to send capture request for order %s. Full amount has not been authorized',
                $order->getIncrementId()
            );
            $this->adyenLogger->error($errorMessage);
            $this->context->getMessageManager()->addErrorMessage(
                __('Full order amount has not been authorized')
            );

            throw new AdyenException($errorMessage);
        }

        $requestCollection = [];
        $adyenOrderPayments = $this->orderPaymentResourceModel->getLinkedAdyenOrderPayments(
            $payment->getEntityId()
        );

        // If the full amount won't be captured OR there are multiple payments to capture
        if (!empty($adyenOrderPayments) && ($amount < $orderAmountCents || count($adyenOrderPayments) > 1)) {
            $requestCollection = $this->buildPartialOrMultipleCaptureData(
                $latestInvoice,
                $payment,
                $amount,
                $currency,
                $adyenOrderPayments
            );
        } else {
            $requestCollection[] = $this->buildCaptureRequestPayload(
                $latestInvoice,
                $payment,
                $amount,
                $currency,
                $pspReference,
                $payment->getOrder()->getTotalInvoiced() ?? 0
            );
        }

        return [
            'body' => $requestCollection,
            'clientConfig' => [
                'storeId' => $payment->getOrder()->getStoreId()
            ]
        ];
    }

    /**
     * Return the data of the multiple capture requests required to capture the full amount OR
     * multiple capture requests required to capture a partial amount OR
     * a single capture request required to capture a partial amount
     *
     * @param Invoice $latestInvoice
     * @param InfoInterface $payment
     * @param int $captureAmountCents
     * @param string $currency
     * @param array $adyenOrderPayments
     * @return array
     * @throws LocalizedException
     */
    private function buildPartialOrMultipleCaptureData(
        Invoice $latestInvoice,
        InfoInterface $payment,
        int $captureAmountCents,
        string $currency,
        array $adyenOrderPayments
    ): array {
        $this->adyenLogger->addAdyenInfoLog(
            sprintf(
                'Building PARTIAL capture request for multiple authorisations, on payment %s',
                $payment->getId()
            ),
            $this->adyenLogger->getOrderContext($payment->getOrder())
        );

        $requestCollection = [];
        $counterAmount = 0;
        $i = 0;

        while ($counterAmount < $captureAmountCents) {
            $adyenOrderPayment = $adyenOrderPayments[$i];
            $paymentAmount = $adyenOrderPayment[OrderPaymentInterface::AMOUNT];
            $totalCaptured = $adyenOrderPayment[OrderPaymentInterface::TOTAL_CAPTURED];
            $availableAmountToCaptureCents = $this->adyenHelper->formatAmount(
                $paymentAmount - $totalCaptured,
                $currency
            );

            // If there is still some amount available to capture
            if ($availableAmountToCaptureCents > 0) {
                // IF the counter amount + available amount to capture from
                // this payment are LESS (or eq) than the capture amount, use the available amount
                // ELSE use only the amount required to complete the full capture
                if ($counterAmount + $availableAmountToCaptureCents <= $captureAmountCents) {
                    $amountCents = $availableAmountToCaptureCents;
                } else {
                    $amountCents = $captureAmountCents - $counterAmount;
                }

                $counterAmount += $amountCents;

                $payload = $this->buildCaptureRequestPayload(
                    $latestInvoice,
                    $payment,
                    $amountCents,
                    $currency,
                    $adyenOrderPayment[OrderPaymentInterface::PSPREFRENCE],
                    $adyenOrderPayment[OrderPaymentInterface::TOTAL_CAPTURED] ?? 0
                );

                $requestCollection[] = $payload;
            }

            $i++;
        }

        return $requestCollection;
    }

    /**
     * @param Invoice $latestInvoice
     * @param InfoInterface $payment
     * @param int $amount
     * @param string $currency
     * @param string $pspReference
     * @param float|null $totalCaptured
     * @return array
     * @throws LocalizedException
     */
    private function buildCaptureRequestPayload(
        Invoice $latestInvoice,
        InfoInterface $payment,
        int $amount,
        string $currency,
        string $pspReference,
        ?float $totalCaptured = null
    ): array {
        $method = $payment->getMethod();
        $storeId = $payment->getOrder()->getStoreId();

        if (isset($method) && $method === 'adyen_moto') {
            $merchantAccount = $payment->getAdditionalInformation('motoMerchantAccount');
        } else {
            $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($method, $storeId);
        }

        $payload = [
            'merchantAccount' => $merchantAccount,
            'amount' => [
                'value' => $amount,
                'currency' => $currency
            ],
            'reference' => $payment->getOrder()->getIncrementId(),
            'paymentPspReference' => $pspReference,
            'idempotencyExtraData' => [
                'totalInvoiced' => $totalCaptured,
                'originalPspReference' => $pspReference
            ]
        ];

        // Build line items
        $paymentMethodInstance = $payment->getMethodInstance();
        if ($this->paymentMethodsHelper->getRequiresLineItems($paymentMethodInstance)) {
            $openInvoiceFields = $this->openInvoiceHelper->getOpenInvoiceDataForInvoice($latestInvoice);
            $payload = array_merge($payload, $openInvoiceFields);
        }

        return $payload;
    }
}

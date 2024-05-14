<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data as DataHelper;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Observer\AdyenPaymentMethodDataAssignObserver;
use Magento\Framework\App\Action\Context;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

/**
 * Class CustomerDataBuilder
 */
class CaptureDataBuilder implements BuilderInterface
{
    private DataHelper $adyenHelper;
    private ChargedCurrency $chargedCurrency;
    private Payment $orderPaymentResourceModel;
    private AdyenOrderPayment $adyenOrderPaymentHelper;
    private AdyenLogger $adyenLogger;
    private Context $context;
    protected OpenInvoice $openInvoiceHelper;

    public function __construct(
        DataHelper $adyenHelper,
        ChargedCurrency $chargedCurrency,
        AdyenOrderPayment $adyenOrderPaymentHelper,
        AdyenLogger $adyenLogger,
        Context $context,
        Payment $orderPaymentResourceModel,
        OpenInvoice $openInvoiceHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->chargedCurrency = $chargedCurrency;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
        $this->adyenLogger = $adyenLogger;
        $this->context = $context;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
        $this->openInvoiceHelper = $openInvoiceHelper;
    }

    /**
     * @throws AdyenException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        /** @var Order $order */
        $order = $payment->getOrder();
        /** @var Invoice $latestInvoice */
        $latestInvoice = $order->getInvoiceCollection()->getLastItem();
        $invoiceAmountCurrency = $this->chargedCurrency->getInvoiceAmountCurrency($latestInvoice);
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order);
        $currency = $invoiceAmountCurrency->getCurrencyCode();
        $amount = $this->adyenHelper->formatAmount($invoiceAmountCurrency->getAmount(), $currency);
        $orderAmountCents = $this->adyenHelper->formatAmount($orderAmountCurrency->getAmount(), $currency);

        $pspReference = $payment->getCcTransId();
        $brandCode = $payment->getAdditionalInformation(AdyenPaymentMethodDataAssignObserver::BRAND_CODE);

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

        $adyenOrderPayments = $this->orderPaymentResourceModel->getLinkedAdyenOrderPayments($payment->getEntityId());
        // If the full amount won't be captured OR there are multiple payments to capture
        if (!empty($adyenOrderPayments) && ($amount < $orderAmountCents || count($adyenOrderPayments) > 1)) {
            return $this->buildPartialOrMultipleCaptureData(
                $payment,
                $currency,
                $adyenOrderPayments,
                $invoiceAmountCurrency->getAmount()
            );
        }

        $modificationAmount = ['value' => $amount, 'currency' => $currency];
        $requestBody = [
            "amount" => $modificationAmount,
            "reference" => $payment->getOrder()->getIncrementId(),
            "paymentPspReference" => $pspReference
        ];

        //Check additionaldata
        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
            $openInvoiceFields = $this->openInvoiceHelper->getOpenInvoiceDataForInvoice($latestInvoice);
            $requestBody = array_merge($requestBody, $openInvoiceFields);
        }
        $request['body'] = $requestBody;
        $request['clientConfig'] = ["storeId" => $payment->getOrder()->getStoreId()];
        $request['headers'] = [
            'idempotencyExtraData' => [
                'totalInvoiced' => $payment->getOrder()->getTotalInvoiced() ?? 0
            ]
        ];

        return $request;
    }

    /**
     * Return the data of the multiple capture requests required to capture the full amount OR
     * multiple capture requests required to capture a partial amount OR
     * a single capture request required to capture a partial amount
     */
    public function buildPartialOrMultipleCaptureData($payment, $currency, $adyenOrderPayments, $captureAmount): array
    {
        $this->adyenLogger->addAdyenInfoLog(sprintf(
            'Building PARTIAL capture request for multiple authorisations, on payment %s',
            $payment->getId()
        ), $this->adyenLogger->getOrderContext($payment->getOrder()));

        $captureAmountCents = $this->adyenHelper->formatAmount($captureAmount, $currency);
        $captureData = [];
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

                $modificationAmount = [
                    'currency' => $currency,
                    'value' => $amountCents
                ];
                $authToCapture = [
                    "amount" => $modificationAmount,
                    "reference" => $payment->getOrder()->getIncrementId(),
                    "paymentPspReference" => $adyenOrderPayment[OrderPaymentInterface::PSPREFRENCE]
                ];

                if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod(
                    $adyenOrderPayment[OrderPaymentInterface::PAYMENT_METHOD]
                )) {
                    $order = $payment->getOrder();
                    $invoices = $order->getInvoiceCollection();
                    // The latest invoice will contain only the selected items(and quantities) for the (partial) capture
                    /** @var Invoice $invoice */
                    $invoice = $invoices->getLastItem();

                    $openInvoiceFields = $this->openInvoiceHelper->getOpenInvoiceDataForInvoice($invoice);
                    $authToCapture = array_merge($authToCapture, $openInvoiceFields);
                }
                $authToCapture['idempotencyExtraData'] = [
                    'totalInvoiced' => $adyenOrderPayment[OrderPaymentInterface::TOTAL_CAPTURED] ?? 0,
                    'originalPspReference' => $adyenOrderPayment[OrderPaymentInterface::PSPREFRENCE]
            ] ;

                $captureData[] = $authToCapture;
            }
            $i++;
        }

        $requestBody = [
            TransactionCapture::MULTIPLE_AUTHORIZATIONS => $captureData
        ];

        $request['body'] = $requestBody;
        $request['clientConfig'] = ["storeId" => $payment->getOrder()->getStoreId()];

        return $request;
    }
}

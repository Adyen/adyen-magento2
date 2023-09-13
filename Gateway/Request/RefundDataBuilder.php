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

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\ResourceModel\Invoice\CollectionFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;
use Adyen\Payment\Observer\AdyenPaymentMethodDataAssignObserver;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Class CustomerDataBuilder
 */
class RefundDataBuilder implements BuilderInterface
{
    const REFUND_STRATEGY_ASCENDING_ORDER = '1';
    const REFUND_STRATEGY_DESCENDING_ORDER = '2';
    const REFUND_STRATEGY_BASED_ON_RATIO = '3';

    private Data $adyenHelper;
    private Config $configHelper;
    private PaymentCollectionFactory $orderPaymentCollectionFactory;
    protected CollectionFactory $adyenInvoiceCollectionFactory;
    private ChargedCurrency $chargedCurrency;

    public function __construct(
        Data $adyenHelper,
        PaymentCollectionFactory   $orderPaymentCollectionFactory,
        CollectionFactory          $adyenInvoiceCollectionFactory,
        ChargedCurrency            $chargedCurrency,
        Config $configHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->orderPaymentCollectionFactory = $orderPaymentCollectionFactory;
        $this->adyenInvoiceCollectionFactory = $adyenInvoiceCollectionFactory;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
    }

    public function build(array $buildSubject): array
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $order = $paymentDataObject->getOrder();
        $payment = $paymentDataObject->getPayment();
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($payment->getOrder(), false);

        // Construct AdyenAmountCurrency from creditmemo
        $creditMemo = $payment->getCreditMemo();
        $creditMemoAmountCurrency = $this->chargedCurrency->getCreditMemoAmountCurrency($creditMemo, false);

        $pspReference = $payment->getCcTransId();
        $currency = $creditMemoAmountCurrency->getCurrencyCode();
        $amount = $creditMemoAmountCurrency->getAmount();

        //Get Merchant Account
        $storeId = $order ->getStoreId();
        $method = $payment->getMethod();
        $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($method, $storeId);

        // check if it contains a partial payment
        $orderPaymentCollection = $this->orderPaymentCollectionFactory
            ->create()
            ->addFieldToFilter('payment_id', $payment->getId());

        // partial refund if multiple payments check refund strategy
        if ($orderPaymentCollection->getSize() > self::REFUND_STRATEGY_ASCENDING_ORDER) {
            $refundStrategy = $this->configHelper->getAdyenAbstractConfigData(
                'partial_payments_refund_strategy',
                $storeId
            );
            $ratio = null;

            if ($refundStrategy == self::REFUND_STRATEGY_ASCENDING_ORDER) {
                // Refund in ascending order
                $orderPaymentCollection->addPaymentFilterAscending($payment->getId());
            } elseif ($refundStrategy == self::REFUND_STRATEGY_DESCENDING_ORDER) {
                // Refund in descending order
                $orderPaymentCollection->addPaymentFilterDescending($payment->getId());
            } elseif ($refundStrategy == self::REFUND_STRATEGY_BASED_ON_RATIO) {
                // refund based on ratio
                $ratio = $amount / $orderAmountCurrency->getAmount();
                $orderPaymentCollection->addPaymentFilterAscending($payment->getId());
            }

            // loop over payment methods and refund them all
            $requestBody = [];
            foreach ($orderPaymentCollection as $partialPayment) {
                // could be that not all the partial payments need a refund
                if ($amount > 0) {
                    if ($ratio) {
                        // refund based on ratio calculate refund amount
                        $modificationAmount = $ratio * (
                                $partialPayment->getAmount() - $partialPayment->getTotalRefunded()
                            );
                    } else {
                        // total authorised amount of the partial payment
                        $partialPaymentAmount = $partialPayment->getAmount() - $partialPayment->getTotalRefunded();

                        // if rest amount is zero go to next payment
                        if (!$partialPaymentAmount > 0) {
                            continue;
                        }

                        // if refunded amount is greater than partial payment amount do a full refund
                        if ($amount >= $partialPaymentAmount) {
                            $modificationAmount = $partialPaymentAmount;
                        } else {
                            $modificationAmount = $amount;
                        }
                        // update amount with rest of the available amount
                        $amount = $amount - $partialPaymentAmount;
                    }

                    $modificationAmountObject = [
                        'currency' => $currency,
                        'value' => $this->adyenHelper->formatAmount($modificationAmount, $currency)
                    ];

                    $requestBody[] = [
                        "merchantAccount" => $merchantAccount,
                        "modificationAmount" => $modificationAmountObject,
                        "reference" => $payment->getOrder()->getIncrementId(),
                        "originalReference" => $partialPayment->getPspreference(),
                    ];
                }
            }
        } else {
            //format the amount to minor units
            $amount = $this->adyenHelper->formatAmount($amount, $currency);
            $modificationAmount = ['currency' => $currency, 'value' => $amount];

            $requestBody = [
                [
                    "merchantAccount" => $merchantAccount,
                    "modificationAmount" => $modificationAmount,
                    "reference" => $payment->getOrder()->getIncrementId(),
                    "originalReference" => $pspReference,
                ]
            ];

            $brandCode = $payment->getAdditionalInformation(
                AdyenPaymentMethodDataAssignObserver::BRAND_CODE
            );

            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
                $openInvoiceFields = $this->getOpenInvoiceData($payment);

                //There is only one payment, so we add the fields to the first(and only) result
                $requestBody[0]["additionalData"] = $openInvoiceFields;
            }
        }
        $request['clientConfig'] = ["storeId" => $payment->getOrder()->getStoreId()];
        $request['body'] = $requestBody;

        $request['headers'] = [
            'idempotencyExtraData' => [
                'totalRefunded' => $payment->getOrder()->getTotalRefunded() ?? 0
            ]
        ];

        return $request;
    }

    protected function getOpenInvoiceData($payment): mixed
    {
        $formFields = [];
        $count = 0;

        // Construct AdyenAmountCurrency from creditmemo
        $creditMemo = $payment->getCreditMemo();

        foreach ($creditMemo->getItems() as $refundItem) {
            $numberOfItems = (int)$refundItem->getQty();
            if ($numberOfItems == 0) {
                continue;
            }

            ++$count;
            $itemAmountCurrency = $this->chargedCurrency->getCreditMemoItemAmountCurrency($refundItem);

            $formFields = $this->adyenHelper->createOpenInvoiceLineItem(
                $formFields,
                $count,
                $refundItem->getName(),
                $itemAmountCurrency->getAmount(),
                $itemAmountCurrency->getCurrencyCode(),
                $itemAmountCurrency->getTaxAmount(),
                $itemAmountCurrency->getAmount() + $itemAmountCurrency->getTaxAmount(),
                $refundItem->getOrderItem()->getTaxPercent(),
                $numberOfItems,
                $payment,
                $refundItem->getId()
            );
        }

        // Shipping cost
        $shippingAmountCurrency = $this->chargedCurrency->getCreditMemoShippingAmountCurrency($creditMemo);
        if ($shippingAmountCurrency->getAmount() > 0) {
            ++$count;
            $formFields = $this->adyenHelper->createOpenInvoiceLineShipping(
                $formFields,
                $count,
                $payment->getOrder(),
                $shippingAmountCurrency->getAmount(),
                $shippingAmountCurrency->getTaxAmount(),
                $shippingAmountCurrency->getCurrencyCode(),
                $payment
            );
        }

        // Adjustment
        $adjustmentAmountCurrency = $this->chargedCurrency->getCreditMemoAdjustmentAmountCurrency($creditMemo);
        if ($adjustmentAmountCurrency->getAmount() != 0) {
            $positive = $adjustmentAmountCurrency->getAmount() > 0 ? 'Positive' : '';
            $negative = $adjustmentAmountCurrency->getAmount() < 0 ? 'Negative' : '';
            $description = "Adjustment - " . implode(' | ', array_filter([$positive, $negative]));

            ++$count;
            $formFields = $this->adyenHelper->createOpenInvoiceLineAdjustment(
                $formFields,
                $count,
                $description,
                $adjustmentAmountCurrency->getAmount(),
                $adjustmentAmountCurrency->getCurrencyCode(),
                $payment
            );
        }

        $formFields['openinvoicedata.numberOfLines'] = $count;

        //Retrieve acquirerReference from the adyen_invoice
        $invoiceId = $creditMemo->getInvoice()->getId();
        $invoices = $this->adyenInvoiceCollectionFactory->create()
            ->addFieldToFilter('invoice_id', $invoiceId);

        $invoice = $invoices->getFirstItem();

        if ($invoice) {
            $formFields['acquirerReference'] = $invoice->getAcquirerReference();
        }

        return $formFields;
    }
}

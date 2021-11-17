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

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\ChargedCurrency;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class CustomerDataBuilder
 */
class RefundDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory
     */
    private $orderPaymentCollectionFactory;

    /**
     * @var \Adyen\Payment\Model\ResourceModel\Invoice\CollectionFactory
     */
    protected $adyenInvoiceCollectionFactory;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * RefundDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $orderPaymentCollectionFactory
     * @param ChargedCurrency $chargedCurrency
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $orderPaymentCollectionFactory,
        \Adyen\Payment\Model\ResourceModel\Invoice\CollectionFactory $adyenInvoiceCollectionFactory,
        ChargedCurrency $chargedCurrency
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->orderPaymentCollectionFactory = $orderPaymentCollectionFactory;
        $this->adyenInvoiceCollectionFactory = $adyenInvoiceCollectionFactory;
        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);

        $order = $paymentDataObject->getOrder();
        $payment = $paymentDataObject->getPayment();
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($payment->getOrder(), false);

        // Construct AdyenAmountCurrency from creditmemo
        $creditMemo = $payment->getCreditMemo();
        $creditMemoAmountCurrency = $this->chargedCurrency->getCreditMemoAmountCurrency($creditMemo, false);

        $pspReference = $payment->getCcTransId();
        $currency = $creditMemoAmountCurrency->getCurrencyCode();
        $amount = $creditMemoAmountCurrency->getAmount();
        $storeId = $order->getStoreId();
        $method = $payment->getMethod();
        $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($method, $storeId);

        // check if it contains a split payment
        $orderPaymentCollection = $this->orderPaymentCollectionFactory
            ->create()
            ->addFieldToFilter('payment_id', $payment->getId());

        // partial refund if multiple payments check refund strategy
        if ($orderPaymentCollection->getSize() > 1) {
            $refundStrategy = $this->adyenHelper->getAdyenAbstractConfigData(
                'split_payments_refund_strategy',
                $order->getStoreId()
            );
            $ratio = null;

            if ($refundStrategy == "1") {
                // Refund in ascending order
                $orderPaymentCollection->addPaymentFilterAscending($payment->getId());
            } elseif ($refundStrategy == "2") {
                // Refund in descending order
                $orderPaymentCollection->addPaymentFilterDescending($payment->getId());
            } elseif ($refundStrategy == "3") {
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
                        "modificationAmount" => $modificationAmountObject,
                        "reference" => $payment->getOrder()->getIncrementId(),
                        "originalReference" => $partialPayment->getPspreference(),
                        "merchantAccount" => $merchantAccount
                    ];
                }
            }
        } else {
            //format the amount to minor units
            $amount = $this->adyenHelper->formatAmount($amount, $currency);
            $modificationAmount = ['currency' => $currency, 'value' => $amount];

            $requestBody = [
                [
                    "modificationAmount" => $modificationAmount,
                    "reference" => $payment->getOrder()->getIncrementId(),
                    "originalReference" => $pspReference,
                    "merchantAccount" => $merchantAccount
                ]
            ];

            $brandCode = $payment->getAdditionalInformation(
                \Adyen\Payment\Observer\AdyenHppDataAssignObserver::BRAND_CODE
            );

            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
                $openInvoiceFields = $this->getOpenInvoiceData($payment);

                //There is only one payment, so we add the fields to the first(and only) result
                $requestBody[0]["additionalData"] = $openInvoiceFields;
            }
        }
        $request['clientConfig'] = ["storeId" => $payment->getOrder()->getStoreId()];
        $request['body'] = $requestBody;
        return $request;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return array|mixed
     */
    protected function getOpenInvoiceData($payment)
    {
        $formFields = [];
        $count = 0;

        // Construct AdyenAmountCurrency from creditmemo
        /**
         * @var \Magento\Sales\Model\Order\Creditmemo $creditMemo
         */
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

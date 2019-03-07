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
     * RefundDataBuilder constructor.
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $orderPaymentCollectionFactory
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $orderPaymentCollectionFactory,
        \Adyen\Payment\Model\ResourceModel\Invoice\CollectionFactory $adyenInvoiceCollectionFactory
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->orderPaymentCollectionFactory = $orderPaymentCollectionFactory;
        $this->adyenInvoiceCollectionFactory = $adyenInvoiceCollectionFactory;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $amount = \Magento\Payment\Gateway\Helper\SubjectReader::readAmount($buildSubject);

        $order = $paymentDataObject->getOrder();
        $payment = $paymentDataObject->getPayment();
        $pspReference = $payment->getCcTransId();
        $currency = $payment->getOrder()->getOrderCurrencyCode();
        $storeId = $order->getStoreId();
        $method = $payment->getMethod();
        $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($method, $storeId);
        $grandTotal = $payment->getOrder()->getGrandTotal();


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
                $ratio = $amount / $grandTotal;
                $orderPaymentCollection->addPaymentFilterAscending($payment->getId());
            }

            // loop over payment methods and refund them all
            $result = [];
            foreach ($orderPaymentCollection as $splitPayment) {
                // could be that not all the split payments need a refund
                if ($amount > 0) {
                    if ($ratio) {
                        // refund based on ratio calculate refund amount
                        $modificationAmount = $ratio * (
                                $splitPayment->getAmount() - $splitPayment->getTotalRefunded()
                            );
                    } else {
                        // total authorised amount of the split payment
                        $splitPaymentAmount = $splitPayment->getAmount() - $splitPayment->getTotalRefunded();

                        // if rest amount is zero go to next payment
                        if (!$splitPaymentAmount > 0) {
                            continue;
                        }

                        // if refunded amount is greater than split payment amount do a full refund
                        if ($amount >= $splitPaymentAmount) {
                            $modificationAmount = $splitPaymentAmount;
                        } else {
                            $modificationAmount = $amount;
                        }
                        // update amount with rest of the available amount
                        $amount = $amount - $splitPaymentAmount;
                    }

                    $modificationAmountObject = [
                        'currency' => $currency,
                        'value' => $this->adyenHelper->formatAmount($modificationAmount, $currency)
                    ];

                    $result[] = [
                        "modificationAmount" => $modificationAmountObject,
                        "reference" => $payment->getOrder()->getIncrementId(),
                        "originalReference" => $splitPayment->getPspreference(),
                        "merchantAccount" => $merchantAccount
                    ];
                }
            }
        } else {
            //format the amount to minor units
            $amount = $this->adyenHelper->formatAmount($amount, $currency);
            $modificationAmount = ['currency' => $currency, 'value' => $amount];

            $result = [
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
                $result[0]["additionalData"] = $openInvoiceFields;
            }
        }

        return $result;
    }


    protected function getOpenInvoiceData($payment)
    {
        $formFields = [];
        $count = 0;
        $currency = $payment->getOrder()->getOrderCurrencyCode();

        /**
         * Magento\Sales\Model\Order\Creditmemo
         */
        $creditMemo = $payment->getCreditMemo();

        foreach ($creditMemo->getAllItems() as $refundItem) {
            ++$count;
            $numberOfItems = (int)$refundItem->getQty();

            $formFields = $this->adyenHelper->createOpenInvoiceLineItem(
                $formFields,
                $count,
                $refundItem->getName(),
                $refundItem->getPrice(),
                $currency,
                $refundItem->getTaxAmount(),
                $refundItem->getPriceInclTax(),
                $refundItem->getTaxPercent(),
                $numberOfItems,
                $payment
            );
        }

        // Shipping cost
        if ($creditMemo->getShippingAmount() > 0) {
            ++$count;
            $formFields = $this->adyenHelper->createOpenInvoiceLineShipping(
                $formFields,
                $count,
                $payment->getOrder(),
                $creditMemo->getShippingAmount(),
                $creditMemo->getShippingTaxAmount(),
                $currency,
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

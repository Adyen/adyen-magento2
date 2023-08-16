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
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Model\ResourceModel\Invoice\CollectionFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;
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

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var PaymentCollectionFactory
     */
    private $orderPaymentCollectionFactory;

    /**
     * @var CollectionFactory
     */
    protected $adyenInvoiceCollectionFactory;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * @var OpenInvoice
     */
    protected $openInvoiceHelper;

    /**
     * RefundDataBuilder constructor.
     *
     * @param Data $adyenHelper
     * @param PaymentCollectionFactory $orderPaymentCollectionFactory
     * @param CollectionFactory $adyenInvoiceCollectionFactory
     * @param ChargedCurrency $chargedCurrency
     * @param OpenInvoice $openInvoiceHelper
     */
    public function __construct(
        Data $adyenHelper,
        PaymentCollectionFactory   $orderPaymentCollectionFactory,
        CollectionFactory          $adyenInvoiceCollectionFactory,
        ChargedCurrency            $chargedCurrency,
        OpenInvoice                $openInvoiceHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->orderPaymentCollectionFactory = $orderPaymentCollectionFactory;
        $this->adyenInvoiceCollectionFactory = $adyenInvoiceCollectionFactory;
        $this->chargedCurrency = $chargedCurrency;
        $this->openInvoiceHelper = $openInvoiceHelper;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
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

        // check if it contains a partial payment
        $orderPaymentCollection = $this->orderPaymentCollectionFactory
            ->create()
            ->addFieldToFilter('payment_id', $payment->getId());

        // partial refund if multiple payments check refund strategy
        if ($orderPaymentCollection->getSize() > self::REFUND_STRATEGY_ASCENDING_ORDER) {
            $refundStrategy = $this->adyenHelper->getAdyenAbstractConfigData(
                'partial_payments_refund_strategy',
                $order->getStoreId()
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
                    "modificationAmount" => $modificationAmount,
                    "reference" => $payment->getOrder()->getIncrementId(),
                    "originalReference" => $pspReference,
                ]
            ];

            $brandCode = $payment->getAdditionalInformation(
                \Adyen\Payment\Observer\AdyenHppDataAssignObserver::BRAND_CODE
            );

            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
                $openInvoiceFields = $this->openInvoiceHelper->getOpenInvoiceData($payment->getOrder());

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

}

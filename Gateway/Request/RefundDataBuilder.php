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

use Adyen\Payment\Api\Data\InvoiceInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\ResourceModel\Invoice\CollectionFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Payment;
use Adyen\Payment\Model\ResourceModel\Invoice\Collection as AdyenInvoiceCollection;

/**
 * Class CustomerDataBuilder
 */
class RefundDataBuilder implements BuilderInterface
{
    const REFUND_STRATEGY_ASCENDING_ORDER = '1';
    const REFUND_STRATEGY_DESCENDING_ORDER = '2';
    const REFUND_STRATEGY_BASED_ON_RATIO = '3';

    /**
     * @param Data $adyenHelper
     * @param PaymentCollectionFactory $orderPaymentCollectionFactory
     * @param ChargedCurrency $chargedCurrency
     * @param Config $configHelper
     * @param OpenInvoice $openInvoiceHelper
     * @param PaymentMethods $paymentMethodsHelper
     * @param AdyenInvoiceCollection $adyenInvoiceCollection
     */
    public function __construct(
        private readonly Data $adyenHelper,
        private readonly PaymentCollectionFactory $orderPaymentCollectionFactory,
        private readonly ChargedCurrency $chargedCurrency,
        private readonly Config $configHelper,
        private readonly OpenInvoice $openInvoiceHelper,
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly AdyenInvoiceCollection $adyenInvoiceCollection,
    ) { }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        /** @var Payment $payment */
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $paymentMethodInstance = $payment->getMethodInstance();

        // Construct AdyenAmountCurrency from creditmemo
        $creditMemo = $payment->getCreditMemo();
        $creditMemoAmountCurrency = $this->chargedCurrency->getCreditMemoAmountCurrency($creditMemo);

        $pspReference = $payment->getCcTransId();
        $currency = $creditMemoAmountCurrency->getCurrencyCode();
        $amount = $creditMemoAmountCurrency->getAmount();


        //Get Merchant Account
        $storeId = $order->getStoreId();
        $method = $payment->getMethod();

        if (isset($method) && $method === 'adyen_moto') {
            $merchantAccount = $payment->getAdditionalInformation('motoMerchantAccount');
        } else {
            $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($method, $storeId);
        }

        // check if it contains a partial payment
        $orderPaymentCollection = $this->orderPaymentCollectionFactory
            ->create()
            ->addFieldToFilter(OrderPaymentInterface::PAYMENT_ID, $payment->getId());

        // partial refund if multiple payments check refund strategy
        if ($orderPaymentCollection->getSize() > 1) {
            $refundStrategy = $this->configHelper->getAdyenAbstractConfigData(
                'partial_payments_refund_strategy',
                $storeId
            );
            $refundBasedOnRatio = false;

            if ($refundStrategy == self::REFUND_STRATEGY_ASCENDING_ORDER) {
                // Refund in ascending order
                $orderPaymentCollection->addPaymentFilterAscending($payment->getId());
            } elseif ($refundStrategy == self::REFUND_STRATEGY_DESCENDING_ORDER) {
                // Refund in descending order
                $orderPaymentCollection->addPaymentFilterDescending($payment->getId());
            } elseif ($refundStrategy == self::REFUND_STRATEGY_BASED_ON_RATIO) {
                // refund based on ratio
                $refundBasedOnRatio = true;
                $orderPaymentCollection->addPaymentFilterAscending($payment->getId());

                $refundableValues = [];
                foreach ($orderPaymentCollection as $partialPayment) {
                    $refundableValues[] = max(0, $this->adyenHelper->formatAmount(
                        (float)$partialPayment->getAmount() - (float)$partialPayment->getTotalRefunded(),
                        $currency
                    ));
                }
                $ratioRefundValues = $this->splitRefundValueByRatio(
                    $this->adyenHelper->formatAmount($amount, $currency),
                    $refundableValues
                );
            }

            // loop over payment methods and refund them all
            $requestBody = [];
            $partialPaymentIndex = -1;
            foreach ($orderPaymentCollection as $partialPayment) {
                $partialPaymentIndex++;
                // could be that not all the partial payments need a refund
                if ($amount > 0) {
                    if ($refundBasedOnRatio) {
                        // refund based on ratio calculate refund amount
                        $modificationValue = $ratioRefundValues[$partialPaymentIndex];

                        if ($modificationValue <= 0) {
                            continue;
                        }
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
                        $modificationValue = $this->adyenHelper->formatAmount($modificationAmount, $currency);
                    }

                    $modificationAmountObject = [
                        'currency' => $currency,
                        'value' => $modificationValue
                    ];

                    $requestBody[] = [
                        "merchantAccount" => $merchantAccount,
                        "amount" => $modificationAmountObject,
                        "reference" => $order->getIncrementId(),
                        "paymentPspReference" => $partialPayment->getPspreference(),
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
                    "amount" => $modificationAmount,
                    "reference" => $order->getIncrementId(),
                    "paymentPspReference" => $pspReference,
                ]
            ];

            $requiresCapturePspreference = $this->paymentMethodsHelper->getRefundRequiresCapturePspreference(
                $paymentMethodInstance
            );

            /*
             * Following section adds `capturePspReference` to the refund request if the payment method requires it.
             * This is used to link the refund to the capture on Adyen on multiple partial capture cases. Note that,
             * AdyenInvoice only exists if the capture mode is manual. Hence, checking the existence of AdyenInvoice
             * is sufficient to determine the capture mode.
             */
            if ($requiresCapturePspreference) {
                $adyenInvoices = $this->adyenInvoiceCollection->getAdyenInvoicesLinkedToMagentoInvoice(
                    $creditMemo->getInvoiceId()
                );
                $firstAdyenInvoice = reset($adyenInvoices);

                if ($firstAdyenInvoice !== false) {
                    $requestBody[0]['capturePspReference'] = $firstAdyenInvoice[InvoiceInterface::PSPREFERENCE];
                }
            }

            if ($this->paymentMethodsHelper->getRequiresLineItems($paymentMethodInstance)) {
                $openInvoiceFieldsCreditMemo = $this->openInvoiceHelper->getOpenInvoiceDataForCreditMemo($creditMemo);
                //There is only one payment, so we add the fields to the first(and only) result
                $requestBody[0] = array_merge($requestBody[0], $openInvoiceFieldsCreditMemo);
            }
        }

        $request['clientConfig'] = ["storeId" => $storeId];
        $request['body'] = $requestBody;

        $request['headers'] = [
            'idempotencyExtraData' => [
                'totalRefunded' => $order->getTotalRefunded() ?? 0
            ]
        ];

        return $request;
    }

    /**
     * Splits a refund value in minor units over the partial payments in proportion to what is left to refund on each.
     * No part exceeds what is left on its payment. The parts add up to the refund value when it is not more than
     * what is left on all payments together, otherwise to what is left.
     *
     * @param int $refundValue
     * @param int[] $refundableValues
     * @return int[]
     */
    private function splitRefundValueByRatio(int $refundValue, array $refundableValues): array
    {
        $refundableTotal = array_sum($refundableValues);
        if ($refundableTotal <= 0) {
            return array_fill_keys(array_keys($refundableValues), 0);
        }

        $splitValues = [];
        foreach ($refundableValues as $key => $refundableValue) {
            $splitValues[$key] = min(
                $refundableValue,
                (int)round($refundValue * $refundableValue / $refundableTotal)
            );
        }

        if (array_sum($splitValues) === $refundValue) {
            return $splitValues;
        }

        // rounding left a difference, so each part is taken from what is still left to split
        foreach ($refundableValues as $key => $refundableValue) {
            $splitValues[$key] = $refundableTotal > 0
                ? min($refundableValue, (int)round($refundValue * $refundableValue / $refundableTotal))
                : 0;
            $refundValue -= $splitValues[$key];
            $refundableTotal -= $refundableValue;
        }

        return $splitValues;
    }
}

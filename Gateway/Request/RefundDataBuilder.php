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
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Model\ResourceModel\Invoice\CollectionFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;
use Adyen\Payment\Observer\AdyenPaymentMethodDataAssignObserver;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Payment;

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
    private ChargedCurrency $chargedCurrency;
    private OpenInvoice $openInvoiceHelper;

    public function __construct(
        Data $adyenHelper,
        PaymentCollectionFactory   $orderPaymentCollectionFactory,
        ChargedCurrency            $chargedCurrency,
        Config                     $configHelper,
        OpenInvoice                $openInvoiceHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->orderPaymentCollectionFactory = $orderPaymentCollectionFactory;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
        $this->openInvoiceHelper = $openInvoiceHelper;
    }

    public function build(array $buildSubject): array
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $order = $paymentDataObject->getOrder();
        /** @var  Payment $payment */
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

        if (isset($method) && $method === 'adyen_moto') {
            $merchantAccount = $payment->getAdditionalInformation('motoMerchantAccount');
        } else {
            $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($method, $storeId);
        }

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
                        "amount" => $modificationAmountObject,
                        "reference" => $payment->getOrder()->getIncrementId(),
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
                    "reference" => $payment->getOrder()->getIncrementId(),
                    "paymentPspReference" => $pspReference,
                ]
            ];

            $brandCode = $payment->getAdditionalInformation(
                AdyenPaymentMethodDataAssignObserver::BRAND_CODE
            );

            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
                $openInvoiceFieldsCreditMemo = $this->openInvoiceHelper->getOpenInvoiceDataForCreditMemo($creditMemo);
                //There is only one payment, so we add the fields to the first(and only) result
                $requestBody[0] =  array_merge($requestBody[0], $openInvoiceFieldsCreditMemo);
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

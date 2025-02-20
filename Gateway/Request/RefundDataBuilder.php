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
     */
    public function __construct(
        private readonly Data $adyenHelper,
        private readonly PaymentCollectionFactory $orderPaymentCollectionFactory,
        private readonly ChargedCurrency $chargedCurrency,
        private readonly Config $configHelper,
        private readonly OpenInvoice $openInvoiceHelper,
        private readonly PaymentMethods $paymentMethodsHelper
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
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);

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
}

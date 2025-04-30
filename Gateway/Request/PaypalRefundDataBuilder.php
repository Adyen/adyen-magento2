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

use Adyen\Payment\Api\Data\InvoiceInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\ResourceModel\Invoice\Collection as AdyenInvoiceCollection;
use Adyen\Payment\Model\ResourceModel\Invoice\CollectionFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection as MagentoCreditMemoCollection;

class PaypalRefundDataBuilder extends RefundDataBuilder implements RefundDataBuilderInterface
{
    /**
     * @param Data $adyenHelper
     * @param PaymentCollectionFactory $orderPaymentCollectionFactory
     * @param ChargedCurrency $chargedCurrency
     * @param Config $configHelper
     * @param OpenInvoice $openInvoiceHelper
     * @param PaymentMethods $paymentMethodsHelper
     * @param MagentoCreditMemoCollection $magentoCreditmemoCollection
     * @param AdyenInvoiceCollection $adyenInvoiceCollection
     * @param ChargedCurrency $chargedCurrencyHelper
     */
    public function __construct(
        private readonly Data $adyenHelper,
        private readonly PaymentCollectionFactory $orderPaymentCollectionFactory,
        private readonly ChargedCurrency $chargedCurrency,
        private readonly Config $configHelper,
        private readonly OpenInvoice $openInvoiceHelper,
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly MagentoCreditMemoCollection $magentoCreditmemoCollection,
        private readonly AdyenInvoiceCollection $adyenInvoiceCollection,
        private readonly ChargedCurrency $chargedCurrencyHelper
    ) {
        parent::__construct(
            $this->adyenHelper,
            $this->orderPaymentCollectionFactory,
            $this->chargedCurrency,
            $this->configHelper,
            $this->openInvoiceHelper,
            $this->paymentMethodsHelper
        );
    }

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
        $storeId = $order->getStoreId();
        $method = $payment->getMethod();

        $isManualCapture = boolval(trim((string) $this->configHelper->getConfigData(
            'paypal_capture_mode',
            'adyen_abstract',
            $order->getStoreId()
        )));

        // check if it contains a partial payment
        $orderPaymentCollection = $this->orderPaymentCollectionFactory
            ->create()
            ->addFieldToFilter(OrderPaymentInterface::PAYMENT_ID, $payment->getId());

        $magentoInvoiceId = $payment->getCreditmemo()->getInvoiceId();
        $adyenInvoices = $this->adyenInvoiceCollection->getAdyenInvoicesLinkedToMagentoInvoice($magentoInvoiceId);

        /* If capture mode is auto, or it's a partial payment, use the parent builder.
         * `capturePspReference` is not required under these circumstances. */
        if ($method !== PaymentMethods::ADYEN_PAYPAL ||
            empty($adyenInvoices) ||
            !$isManualCapture ||
            $orderPaymentCollection->getSize() > 1) {
            return parent::build($buildSubject);
        } else {
            $creditmemoAmountCurrency = $this->chargedCurrencyHelper->getCreditMemoAmountCurrency($payment->getCreditmemo());
            $remainingRefundAmount = $creditmemoAmountCurrency->getAmount();
            $magentoCreditmemoCollectionResult = $this->magentoCreditmemoCollection->getFiltered(['invoice_id' => $magentoInvoiceId]);

            if ($magentoCreditmemoCollectionResult->getSize() > 0) {
                $magentoCreditmemoItems = $magentoCreditmemoCollectionResult->getItems();

                foreach ($magentoCreditmemoItems as $magentoCreditmemoItem) {
                    $remainingRefundAmount -= $magentoCreditmemoItem[CreditmemoInterface::GRAND_TOTAL];
                }
            }

            $firstAdyenInvoice = reset($adyenInvoices);
            $currency = $creditmemoAmountCurrency->getCurrencyCode();
            $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($method, $storeId);

            $orderPaymentCollectionItems = $orderPaymentCollection->getItems();
            $adyenOrderPayment = reset($orderPaymentCollectionItems);

            $requestBody[] = [
                'merchantAccount' => $merchantAccount,
                'amount' => [
                    'value' => $this->adyenHelper->formatAmount($remainingRefundAmount, $currency),
                    'currency' => $currency
                ],
                'reference' => $order->getIncrementId(),
                'paymentPspReference' => $adyenOrderPayment[OrderPaymentInterface::PSPREFRENCE],
                'capturePspReference' => $firstAdyenInvoice[InvoiceInterface::PSPREFERENCE]
            ];

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
}

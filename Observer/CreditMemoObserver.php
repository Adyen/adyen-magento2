<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Invoice as InvoiceHelper;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\StatusResolver;
use Adyen\Payment\Helper\PaymentMethods;

class CreditMemoObserver implements ObserverInterface
{
    /** @var Payment $adyenPaymentResourceModel */
    private $adyenPaymentResourceModel;

    /** @var PaymentFactory */
    private $adyenOrderPaymentFactory;

    /** @var InvoiceHelper $invoiceHelper*/
    private $invoiceHelper;

    /** @var StatusResolver $statusResolver */
    private $statusResolver;

    /** @var AdyenOrderPayment $adyenOrderPaymentHelper */
    private $adyenOrderPaymentHelper;

    /** @var Config $configHelper */
    private $configHelper;

    /** @var PaymentMethods $paymentMethodsHelper */
    private $paymentMethodsHelper;

    /** @var OrderHelper */
    private $orderHelper;

    /**
     * @var AdyenLogger
     */
    private $logger;

    public function __construct(
        Payment $adyenPaymentResourceModel,
        PaymentFactory $adyenOrderPaymentFactory,
        InvoiceHelper $invoiceHelper,
        StatusResolver $statusResolver,
        AdyenOrderPayment $adyenOrderPaymentHelper,
        Config $configHelper,
        AdyenLogger $adyenLogger,
        PaymentMethods $paymentMethodsHelper,
        OrderHelper $orderHelper
    ) {
        $this->adyenPaymentResourceModel = $adyenPaymentResourceModel;
        $this->adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
        $this->invoiceHelper = $invoiceHelper;
        $this->statusResolver = $statusResolver;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
        $this->configHelper = $configHelper;
        $this->logger = $adyenLogger;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Link all adyen_invoices to the appropriate magento invoice and set the order to PROCESSING to allow
     * further invoices to be generated
     *
     * @param Observer $observer
     * @throws AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        $adyenOrderPaymentFactory = $this->adyenOrderPaymentFactory->create();

        /** @var CreditMemo $creditMemo */
        $creditMemo = $observer->getData('creditmemo');
        $order = $invoice->getOrder();
        $payment = $order->getPayment();

        $adyenOrderPayments = $this->adyenPaymentResourceModel->getLinkedAdyenOrderPayments(
            $payment->getEntityId(),
            [OrderPaymentInterface::CAPTURE_STATUS_NO_CAPTURE, OrderPaymentInterface::CAPTURE_STATUS_PARTIAL_CAPTURE]
        );
        foreach ($adyenOrderPayments as $adyenOrderPayment) {
            /** @var \Adyen\Payment\Model\Order\Payment $adyenOrderPaymentObject */
            $adyenOrderPaymentObject = $adyenOrderPaymentFactory->load($adyenOrderPayment[OrderPaymentInterface::ENTITY_ID], OrderPaymentInterface::ENTITY_ID);
            // call creditmemohelper method for linking and updating the credit memos
        }
    }
}

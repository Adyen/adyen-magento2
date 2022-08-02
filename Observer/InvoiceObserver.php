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

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\Order as AdyenOrderHelper;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Invoice as InvoiceHelper;
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

class InvoiceObserver implements ObserverInterface
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

    /** @var AdyenLogger  */
    private $logger;

    /** @var AdyenOrderHelper */
    private $orderHelper;

    public function __construct(
        Payment $adyenPaymentResourceModel,
        PaymentFactory $adyenOrderPaymentFactory,
        InvoiceHelper $invoiceHelper,
        StatusResolver $statusResolver,
        AdyenOrderPayment $adyenOrderPaymentHelper,
        Config $configHelper,
        AdyenLogger $adyenLogger,
        PaymentMethods $paymentMethodsHelper,
        AdyenOrderHelper $orderHelper
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

        /** @var Invoice $invoice */
        $invoice = $observer->getData('invoice');
        $order = $invoice->getOrder();
        $payment = $order->getPayment();
        $method = $payment->getMethod();

        // If payment is not originating from Adyen or invoice has already been paid or full amount is finalized, exit observer
        if (!$this->paymentMethodsHelper->isAdyenPayment($method) || $invoice->wasPayCalled() || $this->adyenOrderPaymentHelper->isFullAmountFinalized($order)) {
            return;
        }


        $this->logger->addAdyenDebug(
            sprintf('Event sales_order_invoice_save_after for invoice %s will be handled', $invoice->getEntityId()),
            array_merge($this->invoiceHelper->getLogInvoiceContext($invoice), $this->orderHelper->getLogOrderContext($order))
        );

        $adyenOrderPayments = $this->adyenPaymentResourceModel->getLinkedAdyenOrderPayments(
            $payment->getEntityId(),
            [OrderPaymentInterface::CAPTURE_STATUS_NO_CAPTURE, OrderPaymentInterface::CAPTURE_STATUS_PARTIAL_CAPTURE]
        );
        foreach ($adyenOrderPayments as $adyenOrderPayment) {
            /** @var \Adyen\Payment\Model\Order\Payment $adyenOrderPaymentObject */
            $adyenOrderPaymentObject = $adyenOrderPaymentFactory->load($adyenOrderPayment[OrderPaymentInterface::ENTITY_ID], OrderPaymentInterface::ENTITY_ID);
            $linkedAmount = $this->invoiceHelper->linkAndUpdateAdyenInvoices($adyenOrderPaymentObject, $invoice);
            $this->adyenOrderPaymentHelper->updatePaymentTotalCaptured($adyenOrderPaymentObject, $linkedAmount);
        }

        $status = $this->configHelper->getConfigData(
            'payment_pre_authorized',
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            $order->getStoreId()
        );

        if (empty($status)) {
            $status = $this->statusResolver->getOrderStatusByState($order, Order::STATE_PENDING_PAYMENT);
        }

        // Set order to PROCESSING to allow further invoices to be generated
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus($status);

        $this->logger->addAdyenDebug(
            sprintf('Event sales_order_invoice_save_after for invoice %s was handled', $invoice->getEntityId()),
            array_merge($this->invoiceHelper->getLogInvoiceContext($invoice), $this->orderHelper->getLogOrderContext($order))
        );
    }
}

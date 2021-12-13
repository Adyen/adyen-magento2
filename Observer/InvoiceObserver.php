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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Model\Invoice as AdyenInvoice;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\Invoice as InvoiceHelper;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order\Invoice;

class InvoiceObserver implements ObserverInterface
{
    /** @var Payment $adyenPaymentResourceModel */
    private $adyenPaymentResourceModel;

    /** @var PaymentFactory */
    private $adyenOrderPaymentFactory;

    /** @var InvoiceHelper $invoiceHelper*/
    private $invoiceHelper;

    /** @var AdyenOrderPayment $adyenOrderPaymentHelper */
    private $adyenOrderPaymentHelper;

    /**
     * InvoiceObserver constructor.
     * @param Payment $adyenPaymentResourceModel
     * @param PaymentFactory $adyenOrderPaymentFactory
     * @param InvoiceHelper $invoiceHelper
     * @param AdyenOrderPayment $adyenOrderPaymentHelper
     */
    public function __construct(
        Payment $adyenPaymentResourceModel,
        PaymentFactory $adyenOrderPaymentFactory,
        InvoiceHelper $invoiceHelper,
        AdyenOrderPayment $adyenOrderPaymentHelper
    ) {
        $this->adyenPaymentResourceModel = $adyenPaymentResourceModel;
        $this->adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
        $this->invoiceHelper = $invoiceHelper;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
    }

    /**
     * Link all adyen_invoices to the appropriate magento invoice and update the total_captured of all the adyen_order_payment
     * entries linked to the payment
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

        $adyenOrderPayments = $this->adyenPaymentResourceModel->getLinkedAdyenOrderPayments(
            $payment->getEntityId(),
            [OrderPaymentInterface::CAPTURE_STATUS_NO_CAPTURE, OrderPaymentInterface::CAPTURE_STATUS_PARTIAL_CAPTURE]
        );

        foreach ($adyenOrderPayments as $adyenOrderPayment) {
            $capturedAmount = 0;
            /** @var \Adyen\Payment\Model\Order\Payment $adyenOrderPaymentObject */
            $adyenOrderPaymentObject = $adyenOrderPaymentFactory->load($adyenOrderPayment[OrderPaymentInterface::ENTITY_ID], OrderPaymentInterface::ENTITY_ID);
            $updatedInvoices = $this->invoiceHelper->linkAndUpdateAdyenInvoices($adyenOrderPaymentObject, $invoice);
            foreach ($updatedInvoices as $updatedInvoice) {
                $capturedAmount += $updatedInvoice->getAmount();
            }
            $this->adyenOrderPaymentHelper->addCaptureData($adyenOrderPaymentObject, $capturedAmount);
        }
    }
}

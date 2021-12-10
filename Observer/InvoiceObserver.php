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

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Model\Invoice as AdyenInvoice;
use Adyen\Payment\Model\ResourceModel\Invoice\Invoice as AdyenInvoiceResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order\Invoice;

class InvoiceObserver implements ObserverInterface
{
    /** @var Payment $adyenPaymentResourceModel */
    private $adyenPaymentResourceModel;

    /** @var AdyenInvoiceResourceModel $adyenInvoiceResourceModel */
    private $adyenInvoiceResourceModel;

    /** @var InvoiceFactory */
    private $adyenInvoiceFactory;

    /**
     * InvoiceObserver constructor.
     * @param Payment $adyenPaymentResourceModel
     * @param AdyenInvoiceResourceModel $adyenInvoiceResourceModel
     */
    public function __construct(
        Payment $adyenPaymentResourceModel,
        AdyenInvoiceResourceModel $adyenInvoiceResourceModel
    ) {
        $this->adyenPaymentResourceModel = $adyenPaymentResourceModel;
        $this->adyenInvoiceResourceModel = $adyenInvoiceResourceModel;
    }

    /**
     * Get all the adyen_invoice entries linked to this order
     *
     * @param Observer $observer
     * @throws AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getData('invoice');
        $order = $invoice->getOrder();
        $payment = $order->getPayment();

        $adyenOrderPayments = $this->adyenPaymentResourceModel->getLinkedAdyenOrderPayments($payment->getEntityId());

        // TODO: Refactor so that we get the adyenInvoices directly from the paymentId
        foreach ($adyenOrderPayments as $adyenOrderPayment) {
            $adyenInvoices = $this->adyenInvoiceResourceModel->getAdyenInvoicesByAdyenPaymentId($adyenOrderPayment[OrderPaymentInterface::ENTITY_ID]);
            foreach ($adyenInvoices as $adyenInvoice) {
                if (is_null($adyenInvoice[AdyenInvoice::INVOICE_ID])) {
                    $invoiceFactory = $this->adyenInvoiceFactory->create();
                    /** @var AdyenInvoice $adyenInvoiceObject */
                    $adyenInvoiceObject = $invoiceFactory->load($adyenInvoice[OrderPaymentInterface::ENTITY_ID], OrderPaymentInterface::ENTITY_ID);
                    $adyenInvoiceObject->setInvoiceId($invoice->getEntityId());
                    $this->adyenInvoiceResourceModel->save($adyenInvoiceObject);
                }
            }
        }
    }
}
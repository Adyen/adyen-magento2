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

namespace Adyen\Payment\Helper;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\InvoiceFactory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as AdyenOrderPaymentCollection;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice as InvoiceModel;

/**
 * Helper class for anything related to the invoice entity
 *
 * @package Adyen\Payment\Helper
 */
class Invoice extends AbstractHelper
{
    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var Data
     */
    protected $adyenDataHelper;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Invoice
     */
    protected $invoiceResourceModel;

    /**
     * @var InvoiceFactory
     */
    protected $adyenInvoiceFactory;

    /**
     * @var \Adyen\Payment\Model\ResourceModel\Invoice\Invoice
     */
    protected $adyenInvoiceResourceModel;

    /**
     * @var OrderPaymentResourceModel
     */
    protected $orderPaymentResourceModel;

    /**
     * Invoice constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Context $context,
        AdyenLogger $adyenLogger,
        Data $adyenDataHelper,
        \Magento\Sales\Model\ResourceModel\Order\Invoice $invoiceResourceModel,
        InvoiceFactory $adyenInvoiceFactory,
        \Adyen\Payment\Model\ResourceModel\Invoice\Invoice $adyenInvoiceResourceModel,
        OrderPaymentResourceModel $orderPaymentResourceModel
    ) {
        parent::__construct($context);
        $this->adyenLogger = $adyenLogger;
        $this->adyenDataHelper = $adyenDataHelper;
        $this->invoiceResourceModel = $invoiceResourceModel;
        $this->adyenInvoiceFactory = $adyenInvoiceFactory;
        $this->adyenInvoiceResourceModel = $adyenInvoiceResourceModel;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
    }

    /**
     * Check if invoice can be set to paid and add entry in adyen_invoice table
     *
     * @param Order $order
     * @param Notification $notification
     * @throws AlreadyExistsException
     */
    public function finalizeInvoice(Order $order, Notification $notification)
    {
        $invoiceCollection = $order->getInvoiceCollection();
        $pspReference = $notification->getPspreference();
        $originalReference = $notification->getOriginalReference();

        foreach ($invoiceCollection as $invoice) {
            // HERE find which order the pspReference relates to (using invoice)


            // Then check if the full amount has been captured. If so, set it to PAID
            $parsedTransId = $this->adyenDataHelper->parseTransactionId($invoice->getTransactionId());
            // UPDATE THIS CHECK
            if (($parsedTransId['pspReference'] ?? '') === $originalReference) {
                $invoice->pay();
                $this->invoiceResourceModel->save($invoice);
            }
        }
    }

    /*
    private function finalizeWithMultiplePayments($adyenOrderPayments, $invoice)
    {

        foreach ($adyenOrderPayments as $adyenOrderPayment) {
            // If adyen order payment is captured
            if (in_array($adyenOrderPayment[OrderPaymentInterface::CAPTURE_STATUS], [
                OrderPaymentInterface::CAPTURE_STATUS_MANUAL_CAPTURE,
                OrderPaymentInterface::CAPTURE_STATUS_AUTO_CAPTURE
            ])) {
                $adyenInvoice = $this->adyenInvoiceFactory->create();
                $adyenInvoice->setInvoiceId($invoice->getEntityId());
                $adyenInvoice->setPspreference($pspReference);
                $adyenInvoice->setOriginalReference($originalReference);
                $adyenInvoice->setAcquirerReference($acquirerReference);
                $this->adyenInvoiceResourceModel->save($adyenInvoice);
            }
        }
    }*/

    /**
     * @param InvoiceModel $invoice
     * @param Notification $notification
     * @return mixed
     * @throws AlreadyExistsException
     */
    public function createAdyenInvoice(InvoiceModel $invoice, Notification $notification)
    {
        $acquirerReference = $additionalData['acquirerReference'] ?? null;
        $pspReference = $notification->getPspreference();
        $originalReference = $notification->getOriginalReference();

        $adyenInvoice = $this->adyenInvoiceFactory->create();
        $adyenInvoice->setInvoiceId($invoice->getEntityId());
        $adyenInvoice->setPspreference($pspReference);
        $adyenInvoice->setOriginalReference($originalReference);
        $adyenInvoice->setAcquirerReference($acquirerReference);
        $this->adyenInvoiceResourceModel->save($adyenInvoice);

        $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
            'Adyen invoice entry created for payment with PSP Reference %s and original reference %s',
            $pspReference,
            $originalReference
        ));

        return $adyenInvoice;
    }

    /**
     * @param Order $order
     * @param Notification $captureNot
     * @return mixed|null
     */
    public function getLinkedInvoiceToCaptureNotification(Order $order, Notification $captureNot)
    {
        $returnInvoice = null;
        $invoiceCollection = $order->getInvoiceCollection();
        $originalReference = $captureNot->getOriginalReference();

        foreach ($invoiceCollection as $invoice) {
            $parsedTransId = $this->adyenDataHelper->parseTransactionId($invoice->getTransactionId());
            if ($parsedTransId['pspReference'] === $originalReference) {
                $returnInvoice = $invoice;
            }
        }

        return $returnInvoice;
    }
}

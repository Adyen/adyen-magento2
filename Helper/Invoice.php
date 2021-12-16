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

use Adyen\Payment\Api\Data\InvoiceInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Invoice as AdyenInvoice;
use Adyen\Payment\Model\InvoiceFactory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Invoice\Invoice as AdyenInvoiceResourceModel;
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
     * @var PaymentFactory
     */
    protected $adyenOrderPaymentFactory;

    /** @var AdyenOrderPayment $adyenOrderPaymentHelper */
    protected $adyenOrderPaymentHelper;

    /**
     * Invoice constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     * @param Data $adyenDataHelper
     * @param InvoiceResourceModel $invoiceResourceModel
     * @param InvoiceFactory $adyenInvoiceFactory
     * @param AdyenInvoiceResourceModel $adyenInvoiceResourceModel
     * @param OrderPaymentResourceModel $orderPaymentResourceModel
     * @param PaymentFactory $paymentFactory
     * @param AdyenOrderPayment $adyenOrderPaymentHelper
     */
    public function __construct(
        Context $context,
        AdyenLogger $adyenLogger,
        Data $adyenDataHelper,
        \Magento\Sales\Model\ResourceModel\Order\Invoice $invoiceResourceModel,
        InvoiceFactory $adyenInvoiceFactory,
        AdyenInvoiceResourceModel $adyenInvoiceResourceModel,
        OrderPaymentResourceModel $orderPaymentResourceModel,
        PaymentFactory $paymentFactory,
        AdyenOrderPayment $adyenOrderPaymentHelper
    ) {
        parent::__construct($context);
        $this->adyenLogger = $adyenLogger;
        $this->adyenDataHelper = $adyenDataHelper;
        $this->invoiceResourceModel = $invoiceResourceModel;
        $this->adyenInvoiceFactory = $adyenInvoiceFactory;
        $this->adyenInvoiceResourceModel = $adyenInvoiceResourceModel;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
        $this->adyenOrderPaymentFactory = $paymentFactory;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
    }

    /**
     * If the full amount has been captured, finalize all linked invoices, else finalize only the invoice linked to
     * this captureNotification. If no invoice linked to this notification is found, log message.
     *
     * @param Order $order
     * @param Notification $captureNotification
     * @param bool $fullAmountCaptured
     * @return array
     * @throws AlreadyExistsException
     */
    public function finalizeInvoices(Order $order, Notification $captureNotification, bool $fullAmountCaptured = false): array
    {
        $finalizedInvoices = [];
        $invoiceCollection = $order->getInvoiceCollection();
        $pspReference = $captureNotification->getPspreference();
        $originalReference = $captureNotification->getOriginalReference();

        foreach ($invoiceCollection as $invoice) {
            $parsedTransId = $this->adyenDataHelper->parseTransactionId($invoice->getTransactionId());
            // Loose comparison based on how Magento does the comparison in entity
            if ($invoice->getState() == InvoiceModel::STATE_OPEN || !$invoice->wasPayCalled()) {
                // If all invoices should be updated, or this is the single invoice that should be updated
                if ($fullAmountCaptured || $parsedTransId['pspReference'] === $originalReference) {
                    $invoice->pay();
                    $this->invoiceResourceModel->save($invoice);
                    $finalizedInvoices[] = $invoice->getEntityId();
                }
            }
        }

        if (empty($finalizedInvoices)) {
            $this->adyenLogger->info(sprintf(
                'No invoice was finalized based on capture with pspReference %s, linked to order %s. ' .
                'This implies that the full amount has not been captured yet and that no invoice is linked to ' .
                'originalReference %s. Once the full amount has been captured, all invoices linked to order %s ' .
                'should be set to PAID',
                $pspReference,
                $order->getIncrementId(),
                $originalReference,
                $order->getIncrementId(),
            ));
        }

        return $finalizedInvoices;
    }

    /**
     * Create an adyen_invoice entry
     *
     * @param Order\Payment $payment
     * @param string $pspReference
     * @param string $originalReference
     * @param int $captureAmountCents
     * @return \Adyen\Payment\Model\Invoice
     * @throws AlreadyExistsException
     */
    public function createAdyenInvoice(Order\Payment $payment, string $pspReference, string $originalReference, int $captureAmountCents): \Adyen\Payment\Model\Invoice
    {
        $order = $payment->getOrder();
        /** @var \Adyen\Payment\Api\Data\OrderPaymentInterface $adyenOrderPayment */
        $adyenOrderPayment = $this->orderPaymentResourceModel->getOrderPaymentDetails($originalReference, $payment->getEntityId());

        /** @var \Adyen\Payment\Model\Invoice $adyenInvoice */
        $adyenInvoice = $this->adyenInvoiceFactory->create();
        $adyenInvoice->setPspreference($pspReference);
        $adyenInvoice->setAdyenPaymentOrderId($adyenOrderPayment[\Adyen\Payment\Api\Data\OrderPaymentInterface::ENTITY_ID]);
        $adyenInvoice->setAmount($this->adyenDataHelper->originalAmount($captureAmountCents, $order->getBaseCurrencyCode()));
        $adyenInvoice->setStatus(InvoiceInterface::STATUS_PENDING_WEBHOOK);
        $this->adyenInvoiceResourceModel->save($adyenInvoice);

        return $adyenInvoice;
    }

    /**
     * Handle a capture webhook notification by updating the acquirerReference and status fields of the adyen_invoice
     * TODO: Handle case where notification is not successful
     *
     * @param Order $order
     * @param Notification $notification
     * @return AdyenInvoice
     * @throws AlreadyExistsException
     * @throws \Exception
     */
    public function handleCaptureWebhook(Order $order, Notification $notification): AdyenInvoice
    {
        $invoiceFactory = $this->adyenInvoiceFactory->create();
        $adyenInvoice = $this->adyenInvoiceResourceModel->getAdyenInvoiceByCaptureWebhook($order, $notification);

        if (is_null($adyenInvoice)) {
            throw new \Exception(sprintf(
                'Unable to find adyen_invoice linked to original reference %s, psp reference %s, and order %s',
                $notification->getOriginalReference(),
                $notification->getPspreference(),
                $order->getIncrementId()
            ));
        }

        /** @var AdyenInvoice $adyenInvoiceObject */
        $adyenInvoiceObject = $invoiceFactory->load($adyenInvoice[InvoiceInterface::ENTITY_ID], InvoiceInterface::ENTITY_ID);

        $additionalData = $notification->getAdditionalData();
        $acquirerReference = $additionalData[Notification::ADDITIONAL_DATA] ?? null;
        $adyenInvoiceObject->setAcquirerReference($acquirerReference);
        $adyenInvoiceObject->setStatus(InvoiceInterface::STATUS_SUCCESSFUL);
        $this->adyenInvoiceResourceModel->save($adyenInvoiceObject);

        return $adyenInvoiceObject;
    }

    /**
     *
     * @param Payment $adyenOrderPayment
     * @param InvoiceModel $invoice
     * @return array
     * @throws AlreadyExistsException
     */
    public function linkAndUpdateAdyenInvoices(Payment $adyenOrderPayment, InvoiceModel $invoice): array
    {
        $invoiceFactory = $this->adyenInvoiceFactory->create();
        $updatedAdyenInvoices = [];
        $capturedAmount = 0;

        $adyenInvoices = $this->adyenInvoiceResourceModel->getAdyenInvoicesByAdyenPaymentId($adyenOrderPayment[OrderPaymentInterface::ENTITY_ID]);
        if (!is_null($adyenInvoices)) {
            foreach ($adyenInvoices as $adyenInvoice) {
                if (is_null($adyenInvoice[AdyenInvoice::INVOICE_ID])) {
                    /** @var AdyenInvoice $adyenInvoiceObject */
                    $adyenInvoiceObject = $invoiceFactory->load($adyenInvoice[InvoiceInterface::ENTITY_ID], InvoiceInterface::ENTITY_ID);
                    $adyenInvoiceObject->setInvoiceId($invoice->getEntityId());
                    $this->adyenInvoiceResourceModel->save($adyenInvoiceObject);
                    $updatedAdyenInvoices[] = $adyenInvoiceObject;
                    $capturedAmount += $adyenInvoiceObject->getAmount();
                }
            }
        }

        $this->adyenOrderPaymentHelper->updatePaymentWithAmount($adyenOrderPayment, $capturedAmount);

        return $updatedAdyenInvoices;
    }
}

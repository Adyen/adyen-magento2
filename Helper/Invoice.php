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
     * Create an adyen_invoice entry and link it to the passed invoice. If no invoice is passed, log message and
     * link to the first invoice in the invoiceCollection
     *
     * @param Order $order
     * @param Notification $notification
     * @param InvoiceModel|null $invoice
     * @return mixed
     * @throws AlreadyExistsException
     */
    public function createAdyenInvoice(Order $order, Notification $notification, InvoiceModel $invoice = null)
    {
        $additionalData = $notification->getAdditionalData();
        $acquirerReference = $additionalData[Notification::ADDITIONAL_DATA] ?? null;
        $pspReference = $notification->getPspreference();
        $originalReference = $notification->getOriginalReference();

        if (is_null($invoice)) {
            $invoiceId = $order->getInvoiceCollection()->getFirstItem()->getEntityId();
            $this->adyenLogger->info(sprintf(
                'Capture with pspReference %s (auth pspReference %s) linked to order %s could not be directly ' .
                'linked to any invoice. This implies that this payment was included in a capture call with other ' .
                'partial authorizations. Hence, capture will be linked by default to invoice %s',
                $pspReference,
                $originalReference,
                $order->getIncrementId(),
                $invoiceId
            ));
        } else {
            $invoiceId = $invoice->getEntityId();
        }

        $adyenInvoice = $this->adyenInvoiceFactory->create();
        $adyenInvoice->setInvoiceId($invoiceId);
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
     * Attempt to get the invoice linked to the capture notification by comparing the notification original reference
     * and the invoice pspReference
     *
     * @param Order $order
     * @param Notification $captureNot
     * @return InvoiceModel|null
     */
    public function getLinkedInvoiceToCaptureNotification(Order $order, Notification $captureNot): ?InvoiceModel
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

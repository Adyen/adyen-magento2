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
use Adyen\Payment\Model\ResourceModel\Invoice\Collection;
use Adyen\Payment\Model\ResourceModel\Invoice\Invoice as AdyenInvoiceResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice as InvoiceModel;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResourceModel;

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
     * @var InvoiceResourceModel
     */
    protected $invoiceResourceModel;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order
     */
    protected $magentoOrderResourceModel;

    /**
     * @var InvoiceFactory
     */
    protected $adyenInvoiceFactory;

    /**
     * @var AdyenInvoiceResourceModel
     */
    protected $adyenInvoiceResourceModel;

    /**
     * @var Collection
     */
    protected $adyenInvoiceCollection;

    /**
     * @var OrderPaymentResourceModel
     */
    protected $orderPaymentResourceModel;

    /**
     * @var PaymentFactory
     */
    protected $adyenOrderPaymentFactory;

    /**
     * @var MagentoInvoiceFactory
     */
    protected $magentoInvoiceFactory;

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
     * @param Collection $adyenInvoiceCollection
     * @param MagentoInvoiceFactory $magentoInvoiceFactory
     * @param \Magento\Sales\Model\ResourceModel\Order $magentoOrderResourceModel
     */
    public function __construct(
        Context $context,
        AdyenLogger $adyenLogger,
        Data $adyenDataHelper,
        InvoiceResourceModel $invoiceResourceModel,
        InvoiceFactory $adyenInvoiceFactory,
        AdyenInvoiceResourceModel $adyenInvoiceResourceModel,
        OrderPaymentResourceModel $orderPaymentResourceModel,
        PaymentFactory $paymentFactory,
        Collection $adyenInvoiceCollection,
        MagentoInvoiceFactory $magentoInvoiceFactory,
        \Magento\Sales\Model\ResourceModel\Order $magentoOrderResourceModel
    ) {
        parent::__construct($context);
        $this->adyenLogger = $adyenLogger;
        $this->adyenDataHelper = $adyenDataHelper;
        $this->invoiceResourceModel = $invoiceResourceModel;
        $this->adyenInvoiceFactory = $adyenInvoiceFactory;
        $this->adyenInvoiceResourceModel = $adyenInvoiceResourceModel;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
        $this->adyenOrderPaymentFactory = $paymentFactory;
        $this->adyenInvoiceCollection = $adyenInvoiceCollection;
        $this->magentoInvoiceFactory = $magentoInvoiceFactory;
        $this->magentoOrderResourceModel = $magentoOrderResourceModel;
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
     * Also if all adyen_invoice entries linked to the magento invoice have been captured, finalize the magento invoice
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

        /** @var InvoiceModel $magentoInvoice */
        $magentoInvoice = $this->magentoInvoiceFactory->create()->load($adyenInvoiceObject->getInvoiceId());

        if ($this->isFullInvoiceAmountManuallyCaptured($magentoInvoice)) {
            $magentoInvoice->pay();
            $this->invoiceResourceModel->save($magentoInvoice);
            $this->magentoOrderResourceModel->save($magentoInvoice->getOrder());
        }

        return $adyenInvoiceObject;
    }

    /**
     * Link all the adyen_invoices related to the adyen_order_payment with the passed invoiceModel
     *
     * @param Payment $adyenOrderPayment
     * @param InvoiceModel $invoice
     * @return float
     * @throws AlreadyExistsException
     */
    public function linkAndUpdateAdyenInvoices(Payment $adyenOrderPayment, InvoiceModel $invoice): float
    {
        $invoiceFactory = $this->adyenInvoiceFactory->create();
        $linkedAmount = 0;

        $adyenInvoices = $this->adyenInvoiceResourceModel->getAdyenInvoicesByAdyenPaymentId($adyenOrderPayment[OrderPaymentInterface::ENTITY_ID]);
        if (!is_null($adyenInvoices)) {
            foreach ($adyenInvoices as $adyenInvoice) {
                if (is_null($adyenInvoice[AdyenInvoice::INVOICE_ID])) {
                    /** @var AdyenInvoice $adyenInvoiceObject */
                    $adyenInvoiceObject = $invoiceFactory->load($adyenInvoice[InvoiceInterface::ENTITY_ID], InvoiceInterface::ENTITY_ID);
                    $adyenInvoiceObject->setInvoiceId($invoice->getEntityId());
                    $this->adyenInvoiceResourceModel->save($adyenInvoiceObject);
                    $linkedAmount += $adyenInvoiceObject->getAmount();
                }
            }
        }

        return $linkedAmount;
    }

    /**
     * Check if the full amount of the invoice has been manually captured
     *
     * @param InvoiceModel $invoice
     * @return bool
     */
    public function isFullInvoiceAmountManuallyCaptured(InvoiceModel $invoice): bool
    {
        $invoiceCapturedAmount = 0;
        $adyenInvoices = $this->adyenInvoiceCollection->getAdyenInvoicesLinkedToMagentoInvoice($invoice->getEntityId());

        foreach ($adyenInvoices as $adyenInvoice) {
            if ($adyenInvoice[InvoiceInterface::STATUS] === InvoiceInterface::STATUS_SUCCESSFUL) {
                $invoiceCapturedAmount += $adyenInvoice[InvoiceInterface::AMOUNT];
            }
        }

        $invoiceAmountCents = $this->adyenDataHelper->formatAmount(
            $invoice->getGrandTotal(),
            $invoice->getOrderCurrencyCode()
        );

        $invoiceCapturedAmountCents = $this->adyenDataHelper->formatAmount(
            $invoiceCapturedAmount,
            $invoice->getOrderCurrencyCode()
        );

        return $invoiceAmountCents === $invoiceCapturedAmountCents;
    }
}

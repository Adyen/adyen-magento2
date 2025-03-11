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
use Adyen\Payment\Exception\AdyenWebhookException;
use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice as InvoiceModel;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * Helper class for anything related to the invoice entity
 *
 * @package Adyen\Payment\Helper
 */
class Invoice extends AbstractHelper
{
    /**
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     * @param Data $adyenDataHelper
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param InvoiceFactory $adyenInvoiceFactory
     * @param AdyenInvoiceResourceModel $adyenInvoiceResourceModel
     * @param OrderPaymentResourceModel $orderPaymentResourceModel
     * @param PaymentFactory $adyenOrderPaymentFactory
     * @param Collection $adyenInvoiceCollection
     * @param MagentoInvoiceFactory $magentoInvoiceFactory
     * @param \Magento\Sales\Model\ResourceModel\Order $magentoOrderResourceModel
     * @param Config $configHelper
     * @param InvoiceSender $invoiceSender
     * @param Transaction $transaction
     * @param ChargedCurrency $chargedCurrencyHelper
     */
    public function __construct(
        protected readonly Context $context,
        protected readonly AdyenLogger $adyenLogger,
        protected readonly Data $adyenDataHelper,
        protected readonly InvoiceRepositoryInterface $invoiceRepository,
        protected readonly InvoiceFactory $adyenInvoiceFactory,
        protected readonly AdyenInvoiceResourceModel $adyenInvoiceResourceModel,
        protected readonly OrderPaymentResourceModel $orderPaymentResourceModel,
        protected readonly PaymentFactory $adyenOrderPaymentFactory,
        protected readonly Collection $adyenInvoiceCollection,
        protected readonly MagentoInvoiceFactory $magentoInvoiceFactory,
        protected readonly \Magento\Sales\Model\ResourceModel\Order $magentoOrderResourceModel,
        protected readonly Config $configHelper,
        protected readonly InvoiceSender $invoiceSender,
        protected readonly Transaction $transaction,
        protected readonly ChargedCurrency $chargedCurrencyHelper
    ) {
        parent::__construct($context);
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param bool $isAutoCapture
     * @return InvoiceModel|null
     * @throws LocalizedException
     */
    public function createInvoice(Order $order, Notification $notification, bool $isAutoCapture): ?InvoiceModel
    {
        $this->adyenLogger->addAdyenNotification(
            'Creating invoice for order',
            [
                'pspReference' => $notification->getPspreference(),
                'merchantReference' => $notification->getMerchantReference()
            ]
        );

        if ($order->canInvoice()) {
            /* We do not use this inside a transaction because order->save()
             * is always done on the end of the notification
             * and it could result in a deadlock see https://github.com/Adyen/magento/issues/334
             */
            try {
                $invoice = $order->prepareInvoice();
                $invoice->getOrder()->setIsInProcess(true);

                // set transaction id so you can do a online refund from credit memo
                $invoice->setTransactionId($notification->getPspreference());


                if ((!$isAutoCapture)) {
                    // if amount is zero create a offline invoice
                    $value = (int)$notification->getAmountValue();
                    if ($value == 0) {
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                    } else {
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::NOT_CAPTURE);
                    }

                    $invoice->register();
                } else {
                    $invoice->register()->pay();
                }

                $this->invoiceRepository->save($invoice);
                $this->adyenLogger->addAdyenNotification(sprintf(
                    'Notification %s created an invoice for order with pspReference %s and merchantReference %s',
                    $notification->getEntityId(),
                    $notification->getPspreference(),
                    $notification->getMerchantReference()
                ),
                    $this->adyenLogger->getInvoiceContext($invoice)
                );
            } catch (Exception $e) {
                $this->adyenLogger->addAdyenNotification(
                    'Error saving invoice: ' . $e->getMessage(),
                    [
                        'pspReference' => $notification->getPspreference(),
                        'merchantReference' => $notification->getMerchantReference()
                    ]

                );
                throw $e;
            }

            $invoiceAutoMail = (bool)$this->scopeConfig->isSetFlag(
                InvoiceIdentity::XML_PATH_EMAIL_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $order->getStoreId()
            );

            if ($invoiceAutoMail) {
                $this->sendInvoiceMail($invoice);
            }

            return $invoice;
        } else {
            $this->adyenLogger->addAdyenNotification(
                sprintf('Unable to create invoice when handling Notification %s', $notification->getEntityId()),
                array_merge($this->adyenLogger->getOrderContext($order), [
                    'pspReference' => $notification->getPspReference(),
                    'canUnhold' => $order->canUnhold(),
                    'isPaymentReview' => $order->isPaymentReview(),
                    'isCancelled' => $order->isCanceled(),
                    'invoiceActionFlag' => $order->getActionFlag(Order::ACTION_FLAG_INVOICE)
                ])
            );

            return null;
        }
    }

    /**
     * Create an adyen_invoice entry
     *
     * @param Order\Payment $payment
     * @param string $pspReference
     * @param string $originalReference
     * @param int $captureAmountCents
     * @param int|null $invoiceId
     * @return AdyenInvoice
     * @throws AlreadyExistsException
     */
    public function createAdyenInvoice(
        Order\Payment $payment,
        string $pspReference,
        string $originalReference,
        int $captureAmountCents,
        int $invoiceId = null
    ): AdyenInvoice {
        $order = $payment->getOrder();
        /** @var OrderPaymentInterface $adyenOrderPayment */
        $adyenOrderPayment = $this->orderPaymentResourceModel->getOrderPaymentDetails($originalReference, $payment->getEntityId());

        $adyenInvoice = $this->adyenInvoiceFactory->create();
        $adyenInvoice->setPspreference($pspReference);
        $adyenInvoice->setAdyenPaymentOrderId($adyenOrderPayment[OrderPaymentInterface::ENTITY_ID]);
        $adyenInvoice->setAmount($this->adyenDataHelper->originalAmount(
            $captureAmountCents,
            $order->getOrderCurrencyCode()
        ));
        $adyenInvoice->setStatus(InvoiceInterface::STATUS_PENDING_WEBHOOK);

        if (isset($invoiceId)) {
            $adyenInvoice->setInvoiceId($invoiceId);
        }

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
     * @throws Exception
     */
    public function handleCaptureWebhook(Order $order, Notification $notification): AdyenInvoice
    {
        $invoiceFactory = $this->adyenInvoiceFactory->create();
        $adyenInvoice = $this->adyenInvoiceResourceModel->getAdyenInvoiceByCaptureWebhook($order, $notification);
        $chargedCurrency = $this->chargedCurrencyHelper->getOrderAmountCurrency($order, false);
        $formattedAdyenOrderAmount = $this->adyenDataHelper->formatAmount(
            $chargedCurrency->getAmount(),
            $chargedCurrency->getCurrencyCode()
        );
        $notificationAmount = $notification->getAmountValue();
        $isFullAmountCaptured = $formattedAdyenOrderAmount == $notificationAmount;

        if (is_null($adyenInvoice) && $order->canInvoice()) {
                if ($isFullAmountCaptured) {
                   $adyenInvoiceObject = $this->createInvoiceFromWebhook($order, $notification);
                } else {
                    $order->addStatusHistoryComment(__(sprintf(
                        'Partial %s webhook notification w/amount %s %s was processed, no invoice created.
                        Please create offline invoice.',
                        $notification->getEventCode(),
                        $notification->getAmountCurrency(),
                        $this->adyenDataHelper->originalAmount(
                            $notification->getAmountValue(),
                            $notification->getAmountCurrency())
                    )), false);
                    throw new AdyenWebhookException(__(sprintf(
                        'Unable to create adyen_invoice from CA partial capture linked to original reference %s,
                        psp reference %s, and order %s.',
                        $notification->getOriginalReference(),
                        $notification->getPspreference(),
                        $order->getIncrementId()
                    )));
            }
        } elseif (is_null($adyenInvoice) && !$order->canInvoice()) {
            throw new AdyenWebhookException(__(sprintf(
                'Unable to find adyen_invoice linked to original reference %s, psp reference %s, and order %s.
                Cannot create invoice.',
                $notification->getOriginalReference(),
                $notification->getPspreference(),
                $order->getIncrementId()
            )));
        }

        /** @var AdyenInvoice $adyenInvoiceObject */
        $adyenInvoiceObject = $adyenInvoiceObject
            ?? $invoiceFactory->load($adyenInvoice[InvoiceInterface::ENTITY_ID], InvoiceInterface::ENTITY_ID);

        $additionalData = $notification->getAdditionalData();
        $acquirerReference = $additionalData[Notification::ADDITIONAL_DATA] ?? null;
        $adyenInvoiceObject->setAcquirerReference($acquirerReference);
        $adyenInvoiceObject->setStatus(InvoiceInterface::STATUS_SUCCESSFUL);
        $this->adyenInvoiceResourceModel->save($adyenInvoiceObject);

        /** @var InvoiceModel $magentoInvoice */
        $magentoInvoice = $this->magentoInvoiceFactory->create()->load($adyenInvoiceObject->getInvoiceId());

        if ($this->isFullInvoiceAmountManuallyCaptured($magentoInvoice)) {
            $magentoInvoice->pay();
            $this->invoiceRepository->save($magentoInvoice);
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

        $invoiceChargedCurrency = $this->chargedCurrencyHelper->getInvoiceAmountCurrency($invoice);

        $invoiceAmountCents = $this->adyenDataHelper->formatAmount(
            $invoiceChargedCurrency->getAmount(),
            $invoiceChargedCurrency->getCurrencyCode()
        );

        $invoiceCapturedAmountCents = $this->adyenDataHelper->formatAmount(
            $invoiceCapturedAmount,
            $invoice->getOrderCurrencyCode()
        );

        return $invoiceAmountCents === $invoiceCapturedAmountCents;
    }

    /**
     * Create both Adyen and Magento invoice from webhook if full amount is manually captured from Adyen CA
     *
     * @param Order $order
     * @param Notification $notification
     * @throws AlreadyExistsException
     * @return AdyenInvoice
     * @throws Exception
     */
    public function createInvoiceFromWebhook(Order $order, Notification $notification): AdyenInvoice
    {
        //Create entry in sales_invoice table
        $invoice = $order->prepareInvoice();
        $invoice->setGrandTotal(
            $this->adyenDataHelper->originalAmount(
                $notification->getAmountValue(),
                $notification->getAmountCurrency()
            ));
        $invoice->setTransactionId($notification->getPspreference());
        $invoice->register();
        $invoice->pay();
        $invoice->save();

        $transactionSave = $this->transaction->addObject(
            $invoice
        );
        $transactionSave->addObject(
            $invoice->getOrder()
        );
        $transactionSave->save();

        //Send Invoice mail to customer
        $invoiceAutoMail = (bool)$this->scopeConfig->isSetFlag(
            InvoiceIdentity::XML_PATH_EMAIL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );

        if ($invoiceAutoMail) {
            $this->sendInvoiceMail($invoice);
            $order->addStatusHistoryComment(
                __('Notified customer about invoice creation #%1.', $invoice->getId())
            );
            $order->setIsCustomerNotified(true);
            $order->save();
        } else {
            $order->addStatusHistoryComment(
                __('Created invoice #%1.', $invoice->getId())
            );
            $order->setIsCustomerNotified(false);
            $order->save();
        }

        //Create entry in adyen_invoice table
        $adyenInvoice = $this->createAdyenInvoice(
            $order->getPayment(),
            $notification->getPspreference(),
            $notification->getOriginalReference(),
            $notification->getAmountValue(),
            $invoice->getId()
        );
        $this->adyenLogger->addAdyenInfoLog(sprintf(
            'Created new adyen_invoice linked to original reference %s, psp reference %s, and order %s.',
            $notification->getOriginalReference(),
            $notification->getPspreference(),
            $order->getIncrementId()
        ));

        return $adyenInvoice;
    }

    public function sendInvoiceMail(InvoiceModel $invoice)
    {
        try {
            $this->invoiceSender->send($invoice);
        } catch (Exception $exception) {
            $this->adyenLogger->addAdyenWarning(
                "Exception in Send Mail in Magento. This is an issue in the the core of Magento" .
                $exception->getMessage()
            );
        }
    }
}

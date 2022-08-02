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
use Adyen\Payment\Helper\Order as AdyenOrderHelper;
use Adyen\Payment\Model\Invoice as AdyenInvoice;
use Adyen\Payment\Model\InvoiceFactory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Invoice\Collection;
use Adyen\Payment\Model\ResourceModel\Invoice\Invoice as AdyenInvoiceResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
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
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var Data
     */
    protected $adyenDataHelper;

    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

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
     * @var Config
     */
    protected $configHelper;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /** @var AdyenOrderHelper */
    private $orderHelper;

    public function __construct(
        Context $context,
        AdyenLogger $adyenLogger,
        Data $adyenDataHelper,
        InvoiceRepositoryInterface $invoiceRepository,
        InvoiceFactory $adyenInvoiceFactory,
        AdyenInvoiceResourceModel $adyenInvoiceResourceModel,
        OrderPaymentResourceModel $orderPaymentResourceModel,
        PaymentFactory $paymentFactory,
        Collection $adyenInvoiceCollection,
        MagentoInvoiceFactory $magentoInvoiceFactory,
        \Magento\Sales\Model\ResourceModel\Order $magentoOrderResourceModel,
        Config $configHelper,
        InvoiceSender $invoiceSender,
        AdyenOrderHelper $orderHelper
    ) {
        parent::__construct($context);
        $this->adyenLogger = $adyenLogger;
        $this->adyenDataHelper = $adyenDataHelper;
        $this->invoiceRepository = $invoiceRepository;
        $this->adyenInvoiceFactory = $adyenInvoiceFactory;
        $this->adyenInvoiceResourceModel = $adyenInvoiceResourceModel;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
        $this->adyenOrderPaymentFactory = $paymentFactory;
        $this->adyenInvoiceCollection = $adyenInvoiceCollection;
        $this->magentoInvoiceFactory = $magentoInvoiceFactory;
        $this->magentoOrderResourceModel = $magentoOrderResourceModel;
        $this->configHelper = $configHelper;
        $this->invoiceSender = $invoiceSender;
        $this->orderHelper = $orderHelper;
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param bool $isAutoCapture
     * @throws LocalizedException
     */
    public function createInvoice(Order $order, Notification $notification, bool $isAutoCapture)
    {
        $this->adyenLogger->addAdyenNotificationCronjob('Creating invoice for order');

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

                $createPendingInvoice = (bool)$this->configHelper->getConfigData(
                    'create_pending_invoice',
                    'adyen_abstract',
                    $order->getStoreId()
                );

                if ((!$isAutoCapture) && ($createPendingInvoice)) {
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
                $this->adyenLogger->addAdyenNotificationCronjob(
                    sprintf('Notification %s created an invoice.', $notification->getEntityId()),
                    $this->getLogInvoiceContext($invoice)
                );
            } catch (Exception $e) {
                $this->adyenLogger->addAdyenNotificationCronjob('Error saving invoice: ' . $e->getMessage());
                throw $e;
            }

            $invoiceAutoMail = (bool)$this->scopeConfig->isSetFlag(
                InvoiceIdentity::XML_PATH_EMAIL_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $order->getStoreId()
            );

            if ($invoiceAutoMail) {
                $this->invoiceSender->send($invoice);
            }
        } else {
            $this->adyenLogger->addAdyenNotificationCronjob(
                sprintf('Unable to create invoice when handling Notification %s', $notification->getEntityId()),
                array_merge($this->orderHelper->getLogOrderContext($order), [
                    'canUnhold' => $order->canUnhold(),
                    'isPaymentReview' => $order->isPaymentReview(),
                    'isCancelled' => $order->isCanceled(),
                    'invoiceActionFlag' => $order->getActionFlag(Order::ACTION_FLAG_INVOICE)
                ])
            );
        }
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

    /**
     * Get the context variables of an invoice to be passed to a log message
     *
     * @param Order\Invoice $invoice
     * @return array
     */
    public function getLogInvoiceContext(Order\Invoice $invoice): array
    {
        $stateName = $invoice->getStateName();

        return [
            'invoiceId' => $invoice->getEntityId(),
            'invoiceIncrementId' => $invoice->getIncrementId(),
            'invoiceState' => $invoice->getState(),
            'invoiceStateName' => $stateName instanceof Phrase ? $stateName->getText() : $stateName,
            'invoiceWasPayCalled' => $invoice->wasPayCalled(),
            'invoiceCanCapture' => $invoice->canCapture(),
            'invoiceCanCancel' => $invoice->canCancel(),
            'invoiceCanVoid' => $invoice->canVoid(),
            'invoiceCanRefund' => $invoice->canRefund()
        ];
    }
}

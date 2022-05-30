<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;


use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\PaymentFactory;
use Exception;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;

class CaptureWebhookHandler implements WebhookHandlerInterface
{
    /** @var WebhookService */
    private $webhookService;

    /** @var Invoice */
    private $invoiceHelper;

    /** @var PaymentFactory */
    private $adyenOrderPaymentFactory;

    /** @var AdyenOrderPayment */
    private $adyenOrderPaymentHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var MagentoInvoiceFactory */
    private $magentoInvoiceFactory;

    public function __construct(
        WebhookService $webhookService,
        Invoice $invoiceHelper,
        PaymentFactory $adyenOrderPaymentFactory,
        AdyenOrderPayment $adyenOrderPaymentHelper,
        AdyenLogger $adyenLogger,
        MagentoInvoiceFactory $magentoInvoiceFactory
    )
    {
        $this->webhookService = $webhookService;
        $this->invoiceHelper = $invoiceHelper;
        $this->adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
        $this->adyenLogger = $adyenLogger;
        $this->magentoInvoiceFactory = $magentoInvoiceFactory;
    }

    public function handleWebhook(Order $order, Notification $notification, string $transitionState): Order
    {
        $isAutoCapture = $this->webhookService->isAutoCapture($order, $notification->getPaymentMethod());

        if ($isAutoCapture) {
            return $order;
        }

        try {
            // TODO Get functionality out of the invoiceHelper function, so we don't have to fetch the order from the db
            $adyenInvoice = $this->invoiceHelper->handleCaptureWebhook($order, $notification);
            // Refresh the order by fetching it from the db
            //$this->setOrderByIncrementId($notification);
            $adyenOrderPayment = $this->adyenOrderPaymentFactory->create()->load($adyenInvoice->getAdyenPaymentOrderId(), OrderPaymentInterface::ENTITY_ID);
            $this->adyenOrderPaymentHelper->refreshPaymentCaptureStatus($adyenOrderPayment, $notification->getAmountCurrency());
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'adyen_invoice %s linked to invoice %s and adyen_order_payment %s was updated',
                $adyenInvoice->getEntityId(),
                $adyenInvoice->getInvoiceId(),
                $adyenInvoice->getAdyenPaymentOrderId()
            ));

            $magentoInvoice = $this->magentoInvoiceFactory->create()->load($adyenInvoice->getInvoiceId(), Order\Invoice::ENTITY_ID);
            $this->adyenLogger->addAdyenNotificationCronjob(
                sprintf('Notification %s updated invoice %s.', $notification->getEntityId(), $magentoInvoice->getEntityid()),
                $this->invoiceHelper->getLogInvoiceContext($magentoInvoice)
            );
        } catch (Exception $e) {
            $this->adyenLogger->addAdyenNotificationCronjob($e->getMessage());
        }

        return $order;
    }
}
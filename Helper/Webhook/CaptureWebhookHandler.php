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
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Webhook\PaymentStates;
use Exception;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Invoice as MagentoInvoice;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;

class CaptureWebhookHandler implements WebhookHandlerInterface
{
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

    /** @var Order */
    private $orderHelper;

    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    public function __construct(
        Invoice $invoiceHelper,
        PaymentFactory $adyenOrderPaymentFactory,
        AdyenOrderPayment $adyenOrderPaymentHelper,
        AdyenLogger $adyenLogger,
        MagentoInvoiceFactory $magentoInvoiceFactory,
        Order $orderHelper,
        PaymentMethods $paymentMethodsHelper
    )
    {
        $this->invoiceHelper = $invoiceHelper;
        $this->adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
        $this->adyenLogger = $adyenLogger;
        $this->magentoInvoiceFactory = $magentoInvoiceFactory;
        $this->orderHelper = $orderHelper;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @param string $transitionState
     * @return MagentoOrder
     */
    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $isAutoCapture = $this->paymentMethodsHelper->isAutoCapture($order, $notification->getPaymentMethod());

        if ($isAutoCapture || $transitionState !== PaymentStates::STATE_PAID) {
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'Capture webhook for order %s was not handled due to AutoCapture %s, OR TransitionState %s',
                $order->getIncrementId(),
                $isAutoCapture,
                $transitionState
            ));

            return $order;
        }

        try {
            // TODO Get functionality out of the invoiceHelper function, so we don't have to fetch the order from the db
            $adyenInvoice = $this->invoiceHelper->handleCaptureWebhook($order, $notification);
            // Refresh the order by fetching it from the db
            $order = $this->orderHelper->fetchOrderByIncrementId($notification);
            $adyenOrderPayment = $this->adyenOrderPaymentFactory->create()->load($adyenInvoice->getAdyenPaymentOrderId(), OrderPaymentInterface::ENTITY_ID);
            $this->adyenOrderPaymentHelper->refreshPaymentCaptureStatus($adyenOrderPayment, $notification->getAmountCurrency());
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'adyen_invoice %s linked to invoice %s and adyen_order_payment %s was updated',
                $adyenInvoice->getEntityId(),
                $adyenInvoice->getInvoiceId(),
                $adyenInvoice->getAdyenPaymentOrderId()
            ));

            $magentoInvoice = $this->magentoInvoiceFactory->create()->load($adyenInvoice->getInvoiceId(), MagentoInvoice::ENTITY_ID);
            $this->adyenLogger->addAdyenNotificationCronjob(
                sprintf('Notification %s updated invoice %s.', $notification->getEntityId(), $magentoInvoice->getEntityid()),
                $this->invoiceHelper->getLogInvoiceContext($magentoInvoice)
            );
        } catch (Exception $e) {
            $this->adyenLogger->addAdyenNotificationCronjob($e->getMessage());
        }

        return $this->orderHelper->finalizeOrder($order, $notification);
    }
}

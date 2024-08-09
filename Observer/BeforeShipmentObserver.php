<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2017 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\Data as AdyenHelper;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Shipment;
use Psr\Log\LoggerInterface;

class BeforeShipmentObserver extends AbstractDataAssignObserver
{
    const XML_ADYEN_ABSTRACT_PREFIX = "adyen_abstract";
    const ONSHIPMENT_CAPTURE_OPENINVOICE = 'onshipment';

    /**
     * @var AdyenHelper
     */
    private AdyenHelper $adyenHelper;

    /**
     * @var ConfigHelper
     */
    private ConfigHelper $configHelper;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var InvoiceRepository
     */
    private InvoiceRepository $invoiceRepository;

    /**
     * @var PaymentMethods
     */
    private PaymentMethods $paymentMethodsHelper;

    /**
     * BeforeShipmentObserver constructor.
     *
     * @param AdyenHelper $adyenHelper
     * @param ConfigHelper $configHelper
     * @param AdyenLogger $logger
     * @param InvoiceRepository $invoiceRepository
     * @param PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
        AdyenHelper $adyenHelper,
        ConfigHelper $configHelper,
        AdyenLogger $logger,
        InvoiceRepository $invoiceRepository,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->invoiceRepository = $invoiceRepository;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    /**
     * @param Observer $observer
     * @throws Exception
     */
    public function execute(Observer $observer): void
    {
        /** @var Shipment $shipment */
        $shipment = $observer->getEvent()->getData('shipment');
        $order = $shipment->getOrder();
        $paymentMethod = $order->getPayment()->getMethod();

        if (!$this->paymentMethodsHelper->isAdyenPayment($paymentMethod)) {
            $this->logger->info(
                "Payment method is not from Adyen for order id {$order->getId()}",
                ['observer' => 'BeforeShipmentObserver']
            );
            return;
        }

        $openInvoiceCapture = $this->configHelper->getConfigData(
            'capture_for_openinvoice',
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $order->getStoreId()
        );

        if (strcmp((string) $openInvoiceCapture, self::ONSHIPMENT_CAPTURE_OPENINVOICE) !== 0)
        {
            $this->logger->info(
                "Capture on shipment not configured for order id {$order->getId()}",
                ['observer' => 'BeforeShipmentObserver']
            );
            return;
        }

        $payment = $order->getPayment();
        $brandCode = $payment->getAdditionalInformation(AdyenPaymentMethodDataAssignObserver::BRAND_CODE);

        if (!$this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
            $this->logger->info(
                "Payment method is from Adyen but isn't OpenInvoice for order id {$order->getId()}",
                ['observer' => 'BeforeShipmentObserver']
            );
            return;
        }

        if (!$order->canInvoice()) {
            $this->logger->info(
                "Cannot invoice order with id {$order->getId()}",
                ['observer' => 'BeforeShipmentObserver']
            );
            return;
        }

        try {
            $itemsToBeInvoiced = $this->itemsToBeInvoiced($shipment);

            $invoice = $order->prepareInvoice($itemsToBeInvoiced);
            $invoice->getOrder()->setIsInProcess(true);

            // set transaction id, so you can do an online refund from credit memo
            $pspReference = $order->getPayment()->getAdyenPspReference();
            $invoice->setTransactionId($pspReference);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $this->invoiceRepository->save($invoice);
        } catch (Exception $e) {
            $this->logger->error($e);
            throw new Exception('Error saving invoice. The error message is: ' . $e->getMessage());
        }
    }

    /**
     * @deprecated Use isAdyenPayment() method from Adyen\Payment\Helper\PaymentMethods.
     *
     * Determine if the payment method is Adyen
     */
    public function isPaymentMethodAdyen($order)
    {
        return strpos((string) $order->getPayment()->getMethod(), 'adyen') !== false;
    }

    /**
     * Builds the invoice item array in the form of "ORDER_ITEM_ID => QTY"
     *
     * @param Shipment $shipment
     * @return array
     */
    private function itemsToBeInvoiced(Shipment $shipment): array
    {
        $shipmentItems = $shipment->getItems();
        $invoiceItems = [];

        foreach ($shipmentItems as $shipmentItem) {
            $invoiceItems[$shipmentItem->getOrderItemId()] = $shipmentItem->getQty();
        }

        return $invoiceItems;
    }
}

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
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Observer\AdyenHppDataAssignObserver;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Shipment;
use Psr\Log\LoggerInterface;
use Throwable;

class BeforeShipmentObserver extends AbstractDataAssignObserver
{
    private $adyenHelper;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var InvoiceRepository
     */
    private $invoiceRepository;

    /**
     * BeforeShipmentObserver constructor.
     *
     * @param AdyenHelper $adyenHelper
     * @param AdyenLogger $logger
     * @param InvoiceRepository $invoiceRepository
     */
    public function __construct(
        AdyenHelper $adyenHelper,
        AdyenLogger $logger,
        InvoiceRepository $invoiceRepository
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->logger = $logger;
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * @param Observer $observer
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        /** @var Shipment $shipment */
        $shipment = $observer->getEvent()->getData('shipment');
        $order = $shipment->getOrder();

        if (!$this->isPaymentMethodAdyen($order)) {
            $this->logger->info(
                "Payment method is not from Adyen for order id {$order->getId()}",
                ['observer' => 'BeforeShipmentObserver']
            );
            return;
        }

        $captureOnShipment = $this->adyenHelper->getConfigData(
            'capture_on_shipment',
            'adyen_abstract',
            $order->getStoreId()
        );

        if (!$captureOnShipment) {
            $this->logger->info(
                "Capture on shipment not configured for order id {$order->getId()}",
                ['observer' => 'BeforeShipmentObserver']
            );
            return;
        }

        $payment = $order->getPayment();
        $brandCode = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE);

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
            $invoice = $order->prepareInvoice();
            $invoice->getOrder()->setIsInProcess(true);

            // set transaction id so you can do a online refund from credit memo
            $pspReference = $order->getPayment()->getAdyenPspReference();
            $invoice->setTransactionId($pspReference);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register()->pay();
            $this->invoiceRepository->save($invoice);
        } catch (Throwable $e) {
            $this->logger->error($e);
            throw new Exception('Error saving invoice. The error message is: ' . $e->getMessage());
        }
    }

    /**
     * Determine if the payment method is Adyen
     */
    public function isPaymentMethodAdyen($order)
    {
        return strpos($order->getPayment()->getMethod(), 'adyen') !== false;
    }
}

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

namespace Adyen\Payment\Observer\Adminhtml;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;

/**
 * Class DataAssignObserver
 */
class BeforeShipmentObserver extends AbstractDataAssignObserver
{

    private $_adyenHelper;

    /**
     * BeforeShipmentObserver constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper
    )
    {
        $this->_adyenHelper = $adyenHelper;
    }

    /**
     * @param Observer $observer
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        $captureOnShipment = $this->_adyenHelper->getConfigData('capture_on_shipment', 'adyen_abstract', $order->getStoreId());

        if ($this->isPaymentMethodAdyen($order) && $captureOnShipment) {

            $payment = $order->getPayment();
            $brandCode = $payment->getAdditionalInformation(
                \Adyen\Payment\Observer\AdyenHppDataAssignObserver::BRAND_CODE
            );

            if ($this->_adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
                if ($order->canInvoice()) {
                    try {
                        $invoice = $order->prepareInvoice();
                        $invoice->getOrder()->setIsInProcess(true);

                        // set transaction id so you can do a online refund from credit memo
                        $pspReference = $order->getPayment()->getAdyenPspReference();
                        $invoice->setTransactionId($pspReference);
                        $invoice->register()->pay();
                        $invoice->save();
                    } catch (Exception $e) {
                        throw new Exception(sprintf('Error saving invoice. The error message is:', $e->getMessage()));
                    }
                }
            }
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

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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class CustomerDataBuilder
 */
class CaptureDataBuilder implements BuilderInterface
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * CaptureDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(\Adyen\Payment\Helper\Data $adyenHelper)
    {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * Create capture request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {

        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $amount = \Magento\Payment\Gateway\Helper\SubjectReader::readAmount($buildSubject);

        $payment = $paymentDataObject->getPayment();

        $pspReference = $payment->getCcTransId();
        $currency = $payment->getOrder()->getOrderCurrencyCode();

        $amount = $this->adyenHelper->formatAmount($amount, $currency);

        $modificationAmount = ['currency' => $currency, 'value' => $amount];
        $requestBody = [
            "modificationAmount" => $modificationAmount,
            "reference" => $payment->getOrder()->getIncrementId(),
            "originalReference" => $pspReference
        ];

        $brandCode = $payment->getAdditionalInformation(
            \Adyen\Payment\Observer\AdyenHppDataAssignObserver::BRAND_CODE
        );

        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
            $openInvoiceFields = $this->getOpenInvoiceData($payment);
            $requestBody["additionalData"] = $openInvoiceFields;
        }
        $request['body'] = $requestBody;
        $request['clientConfig'] = ["storeId" => $payment->getOrder()->getStoreId()];
        return $request;
    }

    /**
     * @param $payment
     * @return mixed
     * @internal param $formFields
     */
    protected function getOpenInvoiceData($payment)
    {
        $formFields = [];
        $count = 0;
        $currency = $payment->getOrder()->getOrderCurrencyCode();

        $invoices = $payment->getOrder()->getInvoiceCollection();

        // The latest invoice will contain only the selected items(and quantities) for the (partial) capture
        $latestInvoice = $invoices->getLastItem();

        foreach ($latestInvoice->getItems() as $invoiceItem) {            
            if($invoiceItem->getOrderItem()->getParentItem()) continue;
            ++$count;
            $numberOfItems = (int)$invoiceItem->getQty();
            $formFields = $this->adyenHelper->createOpenInvoiceLineItem(
                $formFields,
                $count,
                $invoiceItem->getName(),
                $invoiceItem->getPrice(),
                $currency,
                $invoiceItem->getTaxAmount(),
                $invoiceItem->getPriceInclTax(),
                $invoiceItem->getOrderItem()->getTaxPercent(),
                $numberOfItems,
                $payment,
                $invoiceItem->getId()
            );
        }

        // Shipping cost
        if ($latestInvoice->getShippingAmount() > 0) {
            ++$count;
            $formFields = $this->adyenHelper->createOpenInvoiceLineShipping(
                $formFields,
                $count,
                $payment->getOrder(),
                $latestInvoice->getShippingAmount(),
                $latestInvoice->getShippingTaxAmount(),
                $currency,
                $payment
            );
        }

        $formFields['openinvoicedata.numberOfLines'] = $count;

        return $formFields;
    }
}

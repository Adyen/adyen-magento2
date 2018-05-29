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
     * @var \Magento\Tax\Model\Config
     */
    private $taxConfig;

    /**
     * @var \Magento\Tax\Model\Calculation
     */
    private $taxCalculation;

    /**
     * CaptureDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Tax\Model\Config $taxConfig
     * @param \Magento\Tax\Model\Calculation $taxCalculation
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Tax\Model\Config $taxConfig,
        \Magento\Tax\Model\Calculation $taxCalculation
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->taxConfig = $taxConfig;
        $this->taxCalculation = $taxCalculation;

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
        $request = [
            "modificationAmount" => $modificationAmount,
            "reference" => $payment->getOrder()->getIncrementId(),
            "originalReference" => $pspReference,
        ];

        $brandCode = $payment->getAdditionalInformation(
            \Adyen\Payment\Observer\AdyenHppDataAssignObserver::BRAND_CODE
        );

        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) {
            $openInvoiceFields = $this->getOpenInvoiceData($payment);
            $request["additionalData"] = $openInvoiceFields;
        }

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

        foreach ($latestInvoice->getItemsCollection() as $invoiceItem) {
            ++$count;

            $description = str_replace("\n", '', trim($invoiceItem->getName()));
            $itemAmount = $this->adyenHelper->formatAmount($invoiceItem->getPrice(), $currency);

            $itemVatAmount = $this->adyenHelper->getItemVatAmount($invoiceItem->getTaxAmount(),
                $invoiceItem->getPriceInclTax(), $invoiceItem->getPrice(), $currency);

            // Calculate vat percentage
            $itemVatPercentage = $this->adyenHelper->getMinorUnitTaxPercent($invoiceItem->getTaxPercent());

            $numberOfItems = (int)$invoiceItem->getQty();

            $formFields = $this->adyenHelper->getOpenInvoiceLineData($formFields, $count, $currency, $description,
                $itemAmount,
                $itemVatAmount, $itemVatPercentage, $numberOfItems, $payment);
        }

        // Shipping cost
        if ($latestInvoice->getShippingAmount() > 0) {

            ++$count;
            $description = $payment->getOrder()->getShippingDescription();
            $itemAmount = $this->adyenHelper->formatAmount($latestInvoice->getShippingAmount(), $currency);
            $itemVatAmount = $this->adyenHelper->formatAmount($latestInvoice->getShippingTaxAmount(), $currency);

            // Create RateRequest to calculate the Tax class rate for the shipping method
            $rateRequest = $this->taxCalculation->getRateRequest(
                $payment->getOrder()->getShippingAddress(),
                $payment->getOrder()->getBillingAddress(),
                null,
                $payment->getOrder()->getStoreId(), $payment->getOrder()->getCustomerId()
            );

            $taxClassId = $this->taxConfig->getShippingTaxClass($payment->getOrder()->getStoreId());
            $rateRequest->setProductClassId($taxClassId);
            $rate = $this->taxCalculation->getRate($rateRequest);

            $itemVatPercentage = $this->adyenHelper->getMinorUnitTaxPercent($rate);
            $numberOfItems = 1;

            $formFields = $this->adyenHelper->getOpenInvoiceLineData($formFields, $count, $currency, $description,
                $itemAmount,
                $itemVatAmount, $itemVatPercentage, $numberOfItems, $payment);
        }

        $formFields['openinvoicedata.numberOfLines'] = $count;

        return $formFields;
    }
}
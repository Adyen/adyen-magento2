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

use Adyen\Payment\Helper\ChargedCurrency;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Adyen\Payment\Observer\AdyenHppDataAssignObserver;

class CheckoutDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Adyen\Payment\Model\Gender
     */
    private $gender;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Adyen\Payment\Model\Gender $gender
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Adyen\Payment\Model\Gender $gender,
        ChargedCurrency $chargedCurrency
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        $this->cartRepository = $cartRepository;
        $this->gender = $gender;
        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * @param array $buildSubject
     * @return mixed
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();
        $requestBody = [];

        // do not send email
        $order->setCanSendNewEmailFlag(false);

        $requestBodyPaymentMethod['type'] = $payment->getAdditionalInformation(
            AdyenHppDataAssignObserver::BRAND_CODE
        );

        // Additional data for payment methods with issuer list
        if ($payment->getAdditionalInformation(AdyenHppDataAssignObserver::ISSUER_ID)) {
            $requestBodyPaymentMethod['issuer'] = $payment->getAdditionalInformation(
                AdyenHppDataAssignObserver::ISSUER_ID
            );
        }

        $requestBody['returnUrl'] = $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_LINK
            ) . 'adyen/process/result';

        // Additional data for ACH
        if ($payment->getAdditionalInformation("bankAccountNumber")) {
            $requestBody['bankAccount']['bankAccountNumber'] = $payment->getAdditionalInformation("bankAccountNumber");
        }

        if ($payment->getAdditionalInformation("bankLocationId")) {
            $requestBody['bankAccount']['bankLocationId'] = $payment->getAdditionalInformation("bankLocationId");
        }

        if ($payment->getAdditionalInformation("bankAccountOwnerName")) {
            $requestBody['bankAccount']['ownerName'] = $payment->getAdditionalInformation("bankAccountOwnerName");
        }

        // Additional data for open invoice payment
        if ($payment->getAdditionalInformation("gender")) {
            $order->setCustomerGender(
                $this->gender->getMagentoGenderFromAdyenGender(
                    $payment->getAdditionalInformation("gender")
                )
            );
            $requestBodyPaymentMethod['personalDetails']['gender'] = $payment->getAdditionalInformation("gender");
        }

        if ($payment->getAdditionalInformation("dob")) {
            $order->setCustomerDob($payment->getAdditionalInformation("dob"));

            $requestBodyPaymentMethod['personalDetails']['dateOfBirth'] = $this->adyenHelper->formatDate(
                $payment->getAdditionalInformation("dob"),
                'Y-m-d'
            );
        }

        if ($payment->getAdditionalInformation("telephone")) {
            $order->getBillingAddress()->setTelephone($payment->getAdditionalInformation("telephone"));
            $requestBodyPaymentMethod['personalDetails']['telephoneNumber'] = $payment->getAdditionalInformation(
                "telephone"
            );
        }

        if ($payment->getAdditionalInformation("ssn")) {
            $requestBodyPaymentMethod['personalDetails']['socialSecurityNumber'] =
                $payment->getAdditionalInformation("ssn");
        }

        // Additional data for sepa direct debit
        if ($payment->getAdditionalInformation("ownerName")) {
            $requestBodyPaymentMethod['sepa.ownerName'] = $payment->getAdditionalInformation("ownerName");
        }

        if ($payment->getAdditionalInformation("ibanNumber")) {
            $requestBodyPaymentMethod['sepa.ibanNumber'] = $payment->getAdditionalInformation("ibanNumber");
        }

        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod(
                $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
            ) || $this->adyenHelper->isPaymentMethodAfterpayTouchMethod(
                $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
            ) || $this->adyenHelper->isPaymentMethodOneyMethod(
                $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
            )
        ) {
            $openInvoiceFields = $this->getOpenInvoiceData($order);
            $requestBody = array_merge($requestBody, $openInvoiceFields);
        }

        // Ratepay specific Fingerprint
        if ($payment->getAdditionalInformation("df_value") && $this->adyenHelper->isPaymentMethodRatepayMethod(
                $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
            )) {
            $requestBody['deviceFingerprint'] = $payment->getAdditionalInformation("df_value");
        }

        //Boleto data
        if ($payment->getAdditionalInformation("social_security_number")) {
            $requestBody['socialSecurityNumber'] = $payment->getAdditionalInformation("social_security_number");
        }

        if ($payment->getAdditionalInformation("firstname")) {
            $requestBody['shopperName']['firstName'] = $payment->getAdditionalInformation("firstname");
        }

        if ($payment->getAdditionalInformation("lastname")) {
            $requestBody['shopperName']['lastName'] = $payment->getAdditionalInformation("lastname");
        }

        if ($payment->getMethod() == \Adyen\Payment\Model\Ui\AdyenBoletoConfigProvider::CODE) {
            $boletoTypes = $this->adyenHelper->getAdyenBoletoConfigData('boletotypes');
            $boletoTypes = explode(',', $boletoTypes);

            if (count($boletoTypes) == 1) {
                $requestBody['selectedBrand'] = $boletoTypes[0];
                $requestBodyPaymentMethod['type'] = $boletoTypes[0];
            } else {
                $requestBody['selectedBrand'] = $payment->getAdditionalInformation("boleto_type");
                $requestBodyPaymentMethod['type'] = $payment->getAdditionalInformation("boleto_type");
            }

            $deliveryDays = (int)$this->adyenHelper->getAdyenBoletoConfigData("delivery_days", $storeId);
            $deliveryDays = (!empty($deliveryDays)) ? $deliveryDays : 5;
            $deliveryDate = date(
                "Y-m-d\TH:i:s ",
                mktime(
                    date("H"),
                    date("i"),
                    date("s"),
                    date("m"),
                    date("j") + $deliveryDays,
                    date("Y")
                )
            );

            $requestBody['deliveryDate'] = $deliveryDate;

            $order->setCanSendNewEmailFlag(true);
        }

        $requestBody['paymentMethod'] = $requestBodyPaymentMethod;
        $request['body'] = $requestBody;

        return $request;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     */
    protected function getOpenInvoiceData($order): array
    {
        $formFields = [
            'lineItems' => []
        ];

        /** @var \Magento\Quote\Model\Quote $cart */
        $cart = $this->cartRepository->get($order->getQuoteId());
        $amountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order);
        $currency = $amountCurrency->getCurrencyCode();
        $discountAmount = 0;

        foreach ($cart->getAllVisibleItems() as $item) {
            $numberOfItems = (int)$item->getQty();

            $itemAmountCurrency = $this->chargedCurrency->getQuoteItemAmountCurrency($item);

            // Summarize the discount amount item by item
            $discountAmount += $itemAmountCurrency->getDiscountAmount();

            $formattedPriceExcludingTax = $this->adyenHelper->formatAmount(
                $itemAmountCurrency->getAmount(),
                $itemAmountCurrency->getCurrencyCode()
            );

            $formattedTaxAmount = $this->adyenHelper->formatAmount(
                $itemAmountCurrency->getTaxAmount(),
                $itemAmountCurrency->getCurrencyCode()
            );
            $formattedTaxPercentage = $item->getTaxPercent() * 100;

            $formFields['lineItems'][] = [
                'id' => $item->getId(),
                'itemId' => $item->getId(),
                'amountExcludingTax' => $formattedPriceExcludingTax,
                'taxAmount' => $formattedTaxAmount,
                'description' => $item->getName(),
                'quantity' => $numberOfItems,
                'taxCategory' => $item->getProduct()->getAttributeText('tax_class_id'),
                'taxPercentage' => $formattedTaxPercentage
            ];
        }

        // Discount cost
        if ($discountAmount != 0) {
            $description = __('Discount');
            $itemAmount = -$this->adyenHelper->formatAmount($discountAmount, $itemAmountCurrency->getCurrencyCode());
            $itemVatAmount = "0";
            $itemVatPercentage = "0";
            $numberOfItems = 1;

            $formFields['lineItems'][] = [
                'id' => 'Discount',
                'amountExcludingTax' => $itemAmount,
                'taxAmount' => $itemVatAmount,
                'description' => $description,
                'quantity' => $numberOfItems,
                'taxCategory' => 'None',
                'taxPercentage' => $itemVatPercentage
            ];
        }

        // Shipping cost
        if ($cart->getShippingAddress()->getShippingAmount() > 0 || $cart->getShippingAddress()->getShippingTaxAmount(
            ) > 0) {

            $shippingAmountCurrency=$this->chargedCurrency->getQuoteShippingAmountCurrency($cart);

            $priceExcludingTax = $shippingAmountCurrency->getAmount();

            $formattedTaxAmount = $this->adyenHelper->formatAmount(
                $shippingAmountCurrency->getTaxAmount(),
                $currency
            );

            $formattedPriceExcludingTax = $this->adyenHelper->formatAmount($priceExcludingTax, $currency);

            $formattedTaxPercentage = 0;

            if ($priceExcludingTax !== 0) {
                $formattedTaxPercentage = $shippingAmountCurrency->getTaxAmount() / $priceExcludingTax * 100 * 100;
            }

            $formFields['lineItems'][] = [
                'itemId' => 'shippingCost',
                'amountExcludingTax' => $formattedPriceExcludingTax,
                'taxAmount' => $formattedTaxAmount,
                'description' => $order->getShippingDescription(),
                'quantity' => 1,
                'taxPercentage' => $formattedTaxPercentage
            ];
        }

        return $formFields;
    }
}

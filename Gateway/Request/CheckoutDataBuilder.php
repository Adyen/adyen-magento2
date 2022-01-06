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
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\Ui\AdyenBoletoConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Adyen\Payment\Observer\AdyenHppDataAssignObserver;
use Magento\Catalog\Helper\Image;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class CheckoutDataBuilder implements BuilderInterface
{
    const ORDER_EMAIL_REQUIRED_METHODS = [
        AdyenPayByLinkConfigProvider::CODE,
        AdyenBoletoConfigProvider::CODE
    ];

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * @var Image
     */
    private $imageHelper;
    /**
     * @var StateData
     */
    private $stateData;

    /**
     * CheckoutDataBuilder constructor.
     * @param Data $adyenHelper
     * @param StateData $stateData
     * @param CartRepositoryInterface $cartRepository
     * @param ChargedCurrency $chargedCurrency
     * @param Image $imageHelper
     */
    public function __construct(
        Data $adyenHelper,
        StateData $stateData,
        CartRepositoryInterface $cartRepository,
        ChargedCurrency $chargedCurrency,
        Image $imageHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->stateData = $stateData;
        $this->cartRepository = $cartRepository;
        $this->chargedCurrency = $chargedCurrency;
        $this->imageHelper = $imageHelper;
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
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        // Initialize the request body with the current state data
        // Multishipping checkout uses the cc_number field for state data
        $requestBody = $this->stateData->getStateData($order->getQuoteId()) ?:
            json_decode($payment->getCcNumber(), true);

        $order->setCanSendNewEmailFlag(in_array($payment->getMethod(), self::ORDER_EMAIL_REQUIRED_METHODS));

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

        $brandCode = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE);
        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)
            || $this->adyenHelper->isPaymentMethodAfterpayTouchMethod($brandCode)
            || $this->adyenHelper->isPaymentMethodOneyMethod($brandCode)
            || $payment->getMethod() == AdyenPayByLinkConfigProvider::CODE
        ) {
            $openInvoiceFields = $this->getOpenInvoiceData($order);
            $requestBody = array_merge($requestBody, $openInvoiceFields);
        }

        // Ratepay specific Fingerprint
        $deviceFingerprint = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::DF_VALUE);
        if ($deviceFingerprint && $this->adyenHelper->isPaymentMethodRatepayMethod($brandCode)) {
            $requestBody['deviceFingerprint'] = $deviceFingerprint;
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

        if ($payment->getMethod() == AdyenBoletoConfigProvider::CODE) {
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
        }

        // if installments is set and card type is credit card add it into the request
        $numberOfInstallments = $payment->getAdditionalInformation(
            AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS
        ) ?: 0;
        $comboCardType = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::COMBO_CARD_TYPE) ?: 'credit';
        if ($numberOfInstallments > 0) {
            $requestBody['installments']['value'] = $numberOfInstallments;
        }
        // if card type is debit then change the issuer type and unset the installments field
        if ($comboCardType == 'debit') {
            if ($selectedDebitBrand = $this->getSelectedDebitBrand($payment->getAdditionalInformation('cc_type'))) {
                $requestBody['additionalData']['overwriteBrand'] = true;
                $requestBody['selectedBrand'] = $selectedDebitBrand;
                $requestBody['paymentMethod']['type'] = $selectedDebitBrand;
            }
            unset($requestBody['installments']);
        }

        $requestBody['additionalData']['allow3DS2'] = true;

        if (isset($requestBodyPaymentMethod)) {
            $requestBody['paymentMethod'] = $requestBodyPaymentMethod;
        }

        return [
            'body' => $requestBody
        ];
    }

    /**
     * @param string $brand
     * @return string
     */
    private function getSelectedDebitBrand($brand)
    {
        if ($brand == 'VI') {
            return 'electron';
        }
        if ($brand == 'MC') {
            return 'maestro';
        }
        return null;
    }

    /**
     * @param string $item
     * @return string
     */
    protected function getImageUrl($item): string
    {
        $product = $item->getProduct();
        $imageUrl = "";

        if ($image = $product->getSmallImage()) {
            $imageUrl = $this->imageHelper->init($product, 'product_page_image_small')
                ->setImageFile($image)
                ->getUrl();
        }

        return $imageUrl;
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

            $formattedPriceIncludingTax = $this->adyenHelper->formatAmount(
                $itemAmountCurrency->getAmountIncludingTax(),
                $itemAmountCurrency->getCurrencyCode()
            );

            $formattedTaxAmount = $this->adyenHelper->formatAmount(
                $itemAmountCurrency->getTaxAmount(),
                $itemAmountCurrency->getCurrencyCode()
            );

            $formattedTaxPercentage = $this->adyenHelper->formatAmount($item->getTaxPercent(), $currency);

            $formFields['lineItems'][] = [
                'id' => $item->getId(),
                'amountExcludingTax' => $formattedPriceExcludingTax,
                'amountIncludingTax' => $formattedPriceIncludingTax,
                'taxAmount' => $formattedTaxAmount,
                'description' => $item->getName(),
                'quantity' => $numberOfItems,
                'taxCategory' => $item->getProduct()->getAttributeText('tax_class_id'),
                'taxPercentage' => $formattedTaxPercentage,
                'productUrl' => $item->getProduct()->getUrlModel()->getUrl($item->getProduct()),
                'imageUrl' => $this->getImageUrl($item)
            ];
        }

        // Discount cost
        if ($discountAmount != 0) {
            $description = __('Discount');
            $itemAmount = -$this->adyenHelper->formatAmount(
                $discountAmount + $cart->getShippingAddress()->getShippingDiscountAmount(),
                $itemAmountCurrency->getCurrencyCode()
            );
            $itemVatAmount = "0";
            $itemVatPercentage = "0";
            $numberOfItems = 1;

            $formFields['lineItems'][] = [
                'id' => 'Discount',
                'amountExcludingTax' => $itemAmount,
                'amountIncludingTax' => $itemAmount,
                'taxAmount' => $itemVatAmount,
                'description' => $description,
                'quantity' => $numberOfItems,
                'taxCategory' => 'None',
                'taxPercentage' => $itemVatPercentage
            ];
        }

        // Shipping cost
        if ($cart->getShippingAddress()->getShippingAmount() > 0 ||
            $cart->getShippingAddress()->getShippingTaxAmount() > 0
        ) {
            $shippingAmountCurrency = $this->chargedCurrency->getQuoteShippingAmountCurrency($cart);

            $formattedPriceExcludingTax = $this->adyenHelper->formatAmount(
                $shippingAmountCurrency->getAmount(),
                $currency
            );

            $formattedPriceIncludingTax = $this->adyenHelper->formatAmount(
                $shippingAmountCurrency->getAmountIncludingTax(),
                $currency
            );

            $formattedTaxAmount = $this->adyenHelper->formatAmount(
                $shippingAmountCurrency->getTaxAmount(),
                $currency
            );

            $formFields['lineItems'][] = [
                'id' => 'shippingCost',
                'amountExcludingTax' => $formattedPriceExcludingTax,
                'amountIncludingTax' => $formattedPriceIncludingTax,
                'taxAmount' => $formattedTaxAmount,
                'description' => $order->getShippingDescription(),
                'quantity' => 1,
                'taxPercentage' => ($formattedTaxAmount / $formattedPriceExcludingTax) * 100 * 100
            ];
        }

        return $formFields;
    }
}

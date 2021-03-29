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
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
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
     * @var \Magento\Framework\UrlInterface
     */
    private $url;

    /**
     * CheckoutDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Adyen\Payment\Model\Gender $gender
     * @param ChargedCurrency $chargedCurrency
     * @param \Magento\Framework\UrlInterface $url
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Adyen\Payment\Model\Gender $gender,
        ChargedCurrency $chargedCurrency,
        \Magento\Framework\UrlInterface $url
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        $this->cartRepository = $cartRepository;
        $this->gender = $gender;
        $this->chargedCurrency = $chargedCurrency;
        $this->url = $url;
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

        // Initialize the request body with the validated state data
        $requestBody = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::STATE_DATA);

        // do not send email
        $order->setCanSendNewEmailFlag(false);

        $pwaOrigin = $this->adyenHelper->getAdyenAbstractConfigData(
            "payment_origin_url",
            $this->storeManager->getStore()->getId()
        );

        if ($pwaOrigin) {
            $returnUrl = rtrim($pwaOrigin, '/') . '/adyen/process/result?merchantReference=' . $order->getIncrementId();
        } else {
            $this->url->setQueryParam('merchantReference', $order->getIncrementId());
            $returnUrl = $this->url->getUrl("adyen/process/result");
        }

        $requestBody['returnUrl'] = $returnUrl;

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

        if ($this->adyenHelper->isCreditCardThreeDS2Enabled($storeId)) {
            $requestBody['additionalData']['allow3DS2'] = true;
        }

        $requestBody['origin'] = $this->adyenHelper->getOrigin($storeId);
        $requestBody['channel'] = 'web';

        if (isset($requestBodyPaymentMethod)) {
            $requestBody['paymentMethod'] = $requestBodyPaymentMethod;
        }

        $request['body'] = $requestBody;

        return $request;
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
        if ($cart->getShippingAddress()->getShippingAmount() > 0 ||
            $cart->getShippingAddress()->getShippingTaxAmount() > 0
        ) {
            $shippingAmountCurrency = $this->chargedCurrency->getQuoteShippingAmountCurrency($cart);

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
                'id' => 'shippingCost',
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

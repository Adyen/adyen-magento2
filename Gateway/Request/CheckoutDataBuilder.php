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
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        $this->cartRepository = $cartRepository;
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

        $componentStateData = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::STATE_DATA);
        $this->adyenHelper->adyenLogger->addAdyenDebug(json_encode($componentStateData));
        $requestBody = array_merge($requestBody, $componentStateData);
        $this->adyenHelper->adyenLogger->addAdyenDebug(json_encode($requestBody));
        /*foreach ($componentStateData as $key => $data) {

        }*/

        if (empty($requestBody['paymentMethod']['type']) && !empty(
            $payment->getAdditionalInformation(
                AdyenHppDataAssignObserver::BRAND_CODE
            )
            )) {
            $requestBody['paymentMethod']['type'] = $payment->getAdditionalInformation(
                AdyenHppDataAssignObserver::BRAND_CODE
            );
        }

        $requestBody['returnUrl'] = $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_LINK
            ) . 'adyen/process/result';

        // TODO set order payment extra data -> mapping required
        if ($payment->getAdditionalInformation("gender")) {
            $order->setCustomerGender(
                \Adyen\Payment\Model\Gender::getMagentoGenderFromAdyenGender(
                    $payment->getAdditionalInformation("gender")
                )
            );
        }

        if ($payment->getAdditionalInformation("dob")) {
            $order->setCustomerDob($payment->getAdditionalInformation("dob"));
        }

        if ($payment->getAdditionalInformation("telephone")) {
            $order->getBillingAddress()->setTelephone($payment->getAdditionalInformation("telephone"));
        }

        // TODO new function isOpenInvoiceLineItemNeeded
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
        // TODO check if this is still necessary or if there is a component that can handle this
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
            // TODO check if this is coming from the component now
            /*$boletoTypes = $this->adyenHelper->getAdyenBoletoConfigData('boletotypes');
            $boletoTypes = explode(',', $boletoTypes);

            if (count($boletoTypes) == 1) {
                $requestBody['selectedBrand'] = $boletoTypes[0];
                $requestBody['paymentMethod']['type'] = $boletoTypes[0];
            } else {
                $requestBody['selectedBrand'] = $payment->getAdditionalInformation("boleto_type");
                $requestBody['paymentMethod']['type'] = $payment->getAdditionalInformation("boleto_type");
            }*/

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
        $numberOfInstallments = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) ?: 0;
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
        $currency = $cart->getCurrency();
        $discountAmount = 0;

        foreach ($cart->getAllVisibleItems() as $item) {
            $numberOfItems = (int)$item->getQty();

            // Summarize the discount amount item by item
            $discountAmount += $item->getDiscountAmount();

            $formattedPriceExcludingTax = $this->adyenHelper->formatAmount($item->getPrice(), $currency);

            $taxAmount = $item->getPrice() * ($item->getTaxPercent() / 100);
            $formattedTaxAmount = $this->adyenHelper->formatAmount($taxAmount, $currency);
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
            $itemAmount = -$this->adyenHelper->formatAmount($discountAmount, $currency);
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
            $priceExcludingTax = $cart->getShippingAddress()->getShippingAmount() - $cart->getShippingAddress(
                )->getShippingTaxAmount();

            $formattedTaxAmount = $this->adyenHelper->formatAmount(
                $cart->getShippingAddress()->getShippingTaxAmount(),
                $currency
            );

            $formattedPriceExcludingTax = $this->adyenHelper->formatAmount($priceExcludingTax, $currency);

            $formattedTaxPercentage = 0;

            if ($priceExcludingTax !== 0) {
                $formattedTaxPercentage = $cart->getShippingAddress()->getShippingTaxAmount(
                    ) / $priceExcludingTax * 100 * 100;
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
}

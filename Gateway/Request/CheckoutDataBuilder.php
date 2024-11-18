<?php
/**
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
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Model\Config\Source\ThreeDSFlow;
use Adyen\Payment\Model\Ui\AdyenBoletoConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Adyen\Payment\Observer\AdyenHppDataAssignObserver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;

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
     * @var StateData
     */
    private $stateData;

    /** @var Config */
    private $configHelper;

    /** @var OpenInvoice */
    private $openInvoiceHelper;

    /**
     * CheckoutDataBuilder constructor.
     * @param Data $adyenHelper
     * @param StateData $stateData
     * @param CartRepositoryInterface $cartRepository
     * @param ChargedCurrency $chargedCurrency
     * @param Config $configHelper
     * @param OpenInvoice $openInvoiceHelper
     */
    public function __construct(
        Data $adyenHelper,
        StateData $stateData,
        CartRepositoryInterface $cartRepository,
        ChargedCurrency $chargedCurrency,
        Config $configHelper,
        OpenInvoice $openInvoiceHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->stateData = $stateData;
        $this->cartRepository = $cartRepository;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
        $this->openInvoiceHelper = $openInvoiceHelper;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        /** @var Order $order */
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        // Initialize the request body with the current state data
        // Multishipping checkout uses the cc_number field for state data
        $requestBody = $this->stateData->getStateData($order->getQuoteId());

        if (empty($requestBody) && !is_null($payment->getCcNumber())) {
            $requestBody = json_decode($payment->getCcNumber(), true);
        }

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
        if (
            (isset($brandCode) && $this->adyenHelper->isPaymentMethodOpenInvoiceMethod($brandCode)) ||
            $payment->getMethod() === AdyenPayByLinkConfigProvider::CODE
        ) {

            $openInvoiceFields = $this->openInvoiceHelper->getOpenInvoiceDataForOrder($order);
            $requestBody = array_merge($requestBody, $openInvoiceFields);

            if (isset($brandCode) &&
                $this->adyenHelper->isPaymentMethodOfType($brandCode, Data::KLARNA) &&
                $this->configHelper->getAutoCaptureOpenInvoice($storeId)) {
                $requestBody['captureDelayHours'] = 0;
            }

            if (
                (isset($brandCode) && $this->adyenHelper->isPaymentMethodOfType($brandCode, Data::KLARNA)) ||
                $payment->getMethod() === AdyenPayByLinkConfigProvider::CODE
            ) {
                $otherDeliveryInformation = $this->getOtherDeliveryInformation($order);
                if (isset($otherDeliveryInformation)) {
                    $requestBody['additionalData']['openinvoicedata.merchantData'] =
                        base64_encode(json_encode($otherDeliveryInformation));
                }
            }
        }

        // Ratepay specific Fingerprint
        $deviceFingerprint = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::DF_VALUE);
        if ($deviceFingerprint && $this->adyenHelper->isPaymentMethodOfType($brandCode, Data::RATEPAY)) {
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

        /*
         * if the combo card type is debit then add the funding source
         * and unset the installments & brand fields
         */
        if ($comboCardType == 'debit') {
            $requestBody['paymentMethod']['fundingSource'] = 'debit';
            unset($requestBody['paymentMethod']['brand']);
            unset($requestBody['installments']);
        }

        $requestBody['additionalData']['allow3DS2'] =
            $this->configHelper->getThreeDSFlow($storeId) === ThreeDSFlow::THREEDS_NATIVE;

        if (isset($requestBodyPaymentMethod)) {
            $requestBody['paymentMethod'] = $requestBodyPaymentMethod;
        }

        return [
            'body' => $requestBody
        ];
    }

    /**
     * @param Order $order
     * @return array|null
     */
    private function getOtherDeliveryInformation(Order $order): ?array
    {
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress) {
            $otherDeliveryInformation = [
                "shipping_method" => $order->getShippingMethod(),
                "first_name" => $order->getCustomerFirstname(),
                "last_name" => $order->getCustomerLastname(),
                "street_address" => implode(' ', $shippingAddress->getStreet()),
                "postal_code" => $shippingAddress->getPostcode(),
                "city" => $shippingAddress->getCity(),
                "country" => $shippingAddress->getCountryId()
            ];
        }

        return $otherDeliveryInformation ?? null;
    }

}

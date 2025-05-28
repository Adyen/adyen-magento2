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
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\Config\Source\ThreeDSFlow;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Adyen\Payment\Observer\AdyenPaymentMethodDataAssignObserver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Helper\Image;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class CheckoutDataBuilder implements BuilderInterface
{
    /**
     * CheckoutDataBuilder constructor.
     *
     * @param Data $adyenHelper
     * @param StateData $stateData
     * @param CartRepositoryInterface $cartRepository
     * @param ChargedCurrency $chargedCurrency
     * @param Config $configHelper
     * @param PaymentMethods $paymentMethodsHelper
     * @param Image $imageHelper
     */
    public function __construct(
        private readonly Data $adyenHelper,
        private readonly StateData $stateData,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ChargedCurrency $chargedCurrency,
        private readonly Config $configHelper,
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly Image $imageHelper
    ) { }

    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException|LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $paymentMethodInstance = $payment->getMethodInstance();
        /** @var Order $order */
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        // Initialize the request body with the current state data
        // Multishipping checkout uses the cc_number field for state data
        $requestBody = $this->stateData->getStateData($order->getQuoteId());

        if (empty($requestBody) && !is_null($payment->getCcNumber())) {
            $requestBody = json_decode((string) $payment->getCcNumber(), true);
        }

        $order->setCanSendNewEmailFlag(in_array($payment->getMethod(), PaymentMethods::ORDER_EMAIL_REQUIRED_METHODS));

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

        if (
            $this->paymentMethodsHelper->isOpenInvoice($paymentMethodInstance) ||
            $payment->getMethod() === AdyenPayByLinkConfigProvider::CODE
        ) {
            if (str_contains($payment->getMethod(), PaymentMethods::KLARNA) &&
                $this->configHelper->getAutoCaptureOpenInvoice($storeId)) {
                $requestBody['captureDelayHours'] = 0;
            }

            if (str_contains($payment->getMethod(), PaymentMethods::KLARNA) ||
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
        $deviceFingerprint = $payment->getAdditionalInformation(AdyenPaymentMethodDataAssignObserver::DF_VALUE);

        if (
            $deviceFingerprint && str_contains($payment->getMethod(), PaymentMethods::RATEPAY)) {
            $requestBody['deviceFingerprint'] = $deviceFingerprint;
        }

        //Boleto data
        if ($payment->getMethod() == PaymentMethods::ADYEN_BOLETO) {
            $deliveryDays = (int)$this->configHelper->getAdyenBoletoConfigData("delivery_days", $storeId);
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

        $comboCardType = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::COMBO_CARD_TYPE) ?: 'credit';

        /*
         * if the combo card type is debit then add the funding source
         * and unset the installments & brand fields
         */
        if ($comboCardType == 'debit') {
            $requestBody['paymentMethod']['fundingSource'] = 'debit';
            unset($requestBody['paymentMethod']['brand']);
        }

        $threeDSFlow = $this->configHelper->getThreeDSFlow($order->getStoreId());
        $requestBody['authenticationData']['threeDSRequestData']['nativeThreeDS'] =
            $threeDSFlow === ThreeDSFlow::THREEDS_NATIVE ?
                ThreeDSFlow::THREEDS_PREFERRED :
                ThreeDSFlow::THREEDS_DISABLED;

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
}

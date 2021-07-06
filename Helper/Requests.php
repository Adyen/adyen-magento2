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
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Observer\AdyenOneclickDataAssignObserver;
use Adyen\Util\Uuid;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Payment\Model\InfoInterface;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class Requests extends AbstractHelper
{
    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var \Adyen\Payment\Helper\Config
     */
    private $adyenConfig;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @var Address
     */
    private $addressHelper;

    /**
     * Requests constructor.
     *
     * @param Data $adyenHelper
     * @param Config $adyenConfig
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param Address $addressHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Helper\Config $adyenConfig,
        \Magento\Framework\UrlInterface $urlBuilder,
        Address $addressHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenConfig = $adyenConfig;
        $this->urlBuilder = $urlBuilder;
        $this->addressHelper = $addressHelper;
    }

    /**
     * @param $request
     * @param $paymentMethod
     * @param $storeId
     * @return mixed
     */
    public function buildMerchantAccountData($paymentMethod, $storeId, $request = [])
    {
        // Retrieve merchant account
        $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($paymentMethod, $storeId);

        // Assign merchant account to request object
        $request['merchantAccount'] = $merchantAccount;

        return $request;
    }

    /**
     * @param int $customerId
     * @param $billingAddress
     * @param $storeId
     * @param \Magento\Sales\Model\Order\Payment\|null $payment
     * @param null $additionalData
     * @param array $request
     * @return array
     * @return array
     */
    public function buildCustomerData(
        $billingAddress,
        $storeId,
        $customerId = 0,
        $payment = null,
        $additionalData = null,
        $request = []
    ) {
        if ($customerId > 0) {
            $request['shopperReference'] = str_pad($customerId, 3, '0', STR_PAD_LEFT);
        } else {
            $uuid = Uuid::generateV4();
            $guestCustomerId = $payment->getOrder()->getIncrementId() . $uuid;
            $request['shopperReference'] = $guestCustomerId;
        }

        // In case of virtual product and guest checkout there is a workaround to get the guest's email address
        if (!empty($additionalData['guestEmail'])) {
            $request['shopperEmail'] = $additionalData['guestEmail'];
        }

        if (!empty($billingAddress)) {
            if ($customerEmail = $billingAddress->getEmail()) {
                $request['shopperEmail'] = $customerEmail;
            }

            // /paymentLinks is not accepting "telephoneNumber" - FOC-47179
            if (
                $payment->getMethodInstance()->getCode() != AdyenPayByLinkConfigProvider::CODE &&
                $customerTelephone = trim($billingAddress->getTelephone())
            ) {
                $request['telephoneNumber'] = $customerTelephone;
            }

            if ($firstName = $billingAddress->getFirstname()) {
                $request['shopperName']['firstName'] = $firstName;
            }

            if ($lastName = $billingAddress->getLastname()) {
                $request['shopperName']['lastName'] = $lastName;
            }

            if ($countryId = $billingAddress->getCountryId()) {
                $request['countryCode'] = $countryId;
            }

            $request['shopperLocale'] = $this->adyenHelper->getCurrentLocaleCode($storeId);
        }

        return $request;
    }

    /**
     * @param $request
     * @param $ipAddress
     * @return mixed
     */
    public function buildCustomerIpData($ipAddress, $request = [])
    {
        $request['shopperIP'] = $ipAddress;

        return $request;
    }

    /**
     * @param $request
     * @param $billingAddress
     * @param $shippingAddress
     * @return mixed
     */
    public function buildAddressData($billingAddress, $shippingAddress, $storeId, $request = [])
    {
        if ($billingAddress) {
            // Billing address defaults
            $requestBillingDefaults = [
                "street" => "N/A",
                "postalCode" => '',
                "city" => "N/A",
                "houseNumberOrName" => '',
                "country" => "ZZ"
            ];

            // Save the defaults for later to compare if anything has changed
            $requestBilling = $requestBillingDefaults;

            $houseNumberStreetLine = $this->adyenHelper->getConfigData(
                'house_number_street_line',
                'adyen_abstract',
                $storeId
            );

            $customerStreetLinesEnabled = $this->adyenHelper->getCustomerStreetLinesEnabled($storeId);

            $address = $this->addressHelper->getStreetAndHouseNumberFromAddress(
                $billingAddress,
                $houseNumberStreetLine,
                $customerStreetLinesEnabled
            );

            if (!empty($address["name"])) {
                $requestBilling["street"] = $address["name"];
            }

            if (!empty($address["house_number"])) {
                $requestBilling["houseNumberOrName"] = $address["house_number"];
            }

            if (!empty($billingAddress->getCity())) {
                $requestBilling["city"] = $billingAddress->getCity();
            }

            if (!empty($billingAddress->getRegionCode())) {
                $requestBilling["stateOrProvince"] = $billingAddress->getRegionCode();
            }

            if (!empty($billingAddress->getCountryId())) {
                $requestBilling["country"] = $billingAddress->getCountryId();
            }

            if (!empty($billingAddress->getPostcode())) {
                $requestBilling["postalCode"] = $billingAddress->getPostcode();
                if ($billingAddress->getCountryId() == "BR") {
                    $requestBilling["postalCode"] = preg_replace(
                        '/[^\d]/',
                        '',
                        $requestBilling["postalCode"]
                    );
                }
            }

            // If nothing is changed which means delivery address is not filled
            if ($requestBilling !== $requestBillingDefaults) {
                $request['billingAddress'] = $requestBilling;
            }
        }

        if ($shippingAddress) {
            // Delivery address defaults
            $requestDeliveryDefaults = [
                "street" => "N/A",
                "postalCode" => '',
                "city" => "N/A",
                "houseNumberOrName" => '',
                "country" => "ZZ"
            ];

            // Save the defaults for later to compare if anything has changed
            $requestDelivery = $requestDeliveryDefaults;

            // Parse address into street and house number where possible
            $address = $this->addressHelper->getStreetAndHouseNumberFromAddress(
                $shippingAddress,
                $houseNumberStreetLine,
                $customerStreetLinesEnabled
            );

            if (!empty($address['name'])) {
                $requestDelivery["street"] = $address["name"];
            }

            if (!empty($address["house_number"])) {
                $requestDelivery["houseNumberOrName"] = $address["house_number"];
            }

            if (!empty($shippingAddress->getCity())) {
                $requestDelivery["city"] = $shippingAddress->getCity();
            }

            if (!empty($shippingAddress->getRegionCode())) {
                $requestDelivery["stateOrProvince"] = $shippingAddress->getRegionCode();
            }

            if (!empty($shippingAddress->getCountryId())) {
                $requestDelivery["country"] = $shippingAddress->getCountryId();
            }

            if (!empty($shippingAddress->getPostcode())) {
                $requestDelivery["postalCode"] = $shippingAddress->getPostcode();
                if ($shippingAddress->getCountryId() == "BR") {
                    $requestDelivery["postalCode"] = preg_replace(
                        '/[^\d]/',
                        '',
                        $requestDelivery["postalCode"]
                    );
                }
            }

            // If nothing is changed which means delivery address is not filled
            if ($requestDelivery !== $requestDeliveryDefaults) {
                $request['deliveryAddress'] = $requestDelivery;
            }
        }

        return $request;
    }

    /**
     * @param array $request
     * @param $amount
     * @param $currencyCode
     * @param $reference
     * @return array
     */
    public function buildPaymentData($amount, $currencyCode, $reference, array $request = [])
    {
        $request['amount'] = [
            'currency' => $currencyCode,
            'value' => $this->adyenHelper->formatAmount($amount, $currencyCode)
        ];

        $request["reference"] = $reference;

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    public function buildRiskData(array $request = [])
    {
        $request["fraudOffset"] = "0";

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    public function buildBrowserData($request = [])
    {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $request['browserInfo']['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
        }

        if (!empty($_SERVER['HTTP_ACCEPT'])) {
            $request['browserInfo']['acceptHeader'] = $_SERVER['HTTP_ACCEPT'];
        }

        return $request;
    }

    /**
     * @param InfoInterface $payment
     * @param array $request
     * @return array
     */
    public function buildRedirectData($payment, $request = [])
    {
        $request['returnUrl'] = rtrim(
                $this->adyenHelper->getOrigin($payment->getMethodInstance()->getStore()), '/'
            ) .
            '/adyen/process/result?merchantReference=' . $payment->getOrder()->getIncrementId();
        return $request;
    }

    /**
     * @param $request
     * @param $areaCode
     * @param $storeId
     * @param $payment
     */
    public function buildRecurringData(int $storeId, $payment, $request = [])
    {
        $enableOneclick = $this->adyenHelper->getAdyenAbstractConfigData('enable_oneclick', $storeId);
        $enableVault = $this->adyenHelper->isCreditCardVaultEnabled();
        $storedPaymentMethodsEnabled = $this->adyenHelper->getAdyenOneclickConfigData('active', $storeId);

        // TODO Remove it in version 7
        if ($payment->getAdditionalInformation(AdyenCcDataAssignObserver::STORE_CC)) {
            $request['storePaymentMethod'] = true;
        }
        //recurring
        if ($storedPaymentMethodsEnabled) {
            if ($enableVault) {
                $request['recurringProcessingModel'] = 'Subscription';
            } else {
                if ($enableOneclick) {
                    $request['recurringProcessingModel'] = 'CardOnFile';
                } else {
                    $request['recurringProcessingModel'] = 'Subscription';
                }
            }
        }

        return $request;
    }

    /**
     * @param $request
     * @param $payment
     * @param $storeIdbuildCCData
     * @return mixed
     */
    public function buildCCData($payload, $storeId, $areaCode, $request = [])
    {
        // If ccType is set use this. For bcmc you need bcmc otherwise it will fail

        if (!empty($payload['method']) && $payload['method'] == 'adyen_oneclick' &&
            !empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]['variant'])
        ) {
            $request['paymentMethod']['type'] = $payload[PaymentInterface::KEY_ADDITIONAL_DATA]['variant'];
        } else {
            $request['paymentMethod']['type'] = 'scheme';
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER]) &&
            $cardNumber = $payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER]) {
            $request['paymentMethod']['encryptedCardNumber'] = $cardNumber;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH]) &&
            $expiryMonth = $payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH]) {
            $request['paymentMethod']['encryptedExpiryMonth'] = $expiryMonth;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR]) &&
            $expiryYear = $payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR]) {
            $request['paymentMethod']['encryptedExpiryYear'] = $expiryYear;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenCcDataAssignObserver::HOLDER_NAME]) && $holderName =
                $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::HOLDER_NAME]) {
            $request['paymentMethod']['holderName'] = $holderName;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE]) &&
            $securityCode = $payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE]) {
            $request['paymentMethod']['encryptedSecurityCode'] = $securityCode;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenOneclickDataAssignObserver::RECURRING_DETAIL_REFERENCE]) &&
            $recurringDetailReference = $payload[PaymentInterface::KEY_ADDITIONAL_DATA]
            [AdyenOneclickDataAssignObserver::RECURRING_DETAIL_REFERENCE]
        ) {
            $request['paymentMethod']['recurringDetailReference'] = $recurringDetailReference;
        }

        /**
         * if MOTO for backend is enabled use MOTO as shopper interaction type
         */
        $enableMoto = $this->adyenHelper->getAdyenCcConfigDataFlag('enable_moto', $storeId);
        if ($areaCode === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE &&
            $enableMoto
        ) {
            $request['shopperInteraction'] = "Moto";
        }

        // if installments is set add it into the request
        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]
        [AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS])) {
            if (($numberOfInstallment = $payload[PaymentInterface::KEY_ADDITIONAL_DATA]
                [AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS]) > 0) {
                $request['installments']['value'] = $numberOfInstallment;
            }
        }

        return $request;
    }
}

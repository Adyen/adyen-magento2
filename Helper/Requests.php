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

use Adyen\Payment\Observer\AdyenOneclickDataAssignObserver;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Vault\Model\Ui\VaultConfigProvider;

use Adyen\Payment\Observer\AdyenHppDataAssignObserver;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class Requests extends AbstractHelper
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * Requests constructor.
     *
     * @param Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param $request
     * @param $paymentMethod
     * @param $storeId
     * @return mixed
     */
    public function buildMerchantAccountData($request = [], $paymentMethod, $storeId)
    {
        // Retrieve merchant account
        $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($paymentMethod, $storeId);

        // Assign merchant account to request object
        $request['merchantAccount'] = $merchantAccount;

        return $request;
    }

    /**
     * @param array $request
     * @param int $customerId
     * @param $billingAddress
     * @param $storeId
     * @param null $payment
     * @param null $additionalData
     * @return array
     */
    public function buildCustomerData($request = [], $customerId = 0, $billingAddress, $storeId, $payment = null, $additionalData = null)
    {
        if ($customerId > 0) {
            $request['shopperReference'] = $customerId;
        }

        $paymentMethod = '';
        if ($payment) {
            $paymentMethod = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE);
        }

        // In case of virtual product and guest checkout there is a workaround to get the guest's email address
        if (!empty($additionalData['guestEmail'])) {
            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethod) &&
                !$this->adyenHelper->isPaymentMethodAfterpayTouchMethod($paymentMethod)
            ) {
                $request['paymentMethod']['personalDetails']['shopperEmail'] = $additionalData['guestEmail'];
            } else {
                $request['shopperEmail'] = $additionalData['guestEmail'];
            }
        }

        if (!empty($billingAddress)) {
            // Openinvoice (klarna and afterpay BUT not afterpay touch) methods requires different request format
            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethod) &&
                !$this->adyenHelper->isPaymentMethodAfterpayTouchMethod($paymentMethod)
            ) {
                if ($customerEmail = $billingAddress->getEmail()) {
                    $request['paymentMethod']['personalDetails']['shopperEmail'] = $customerEmail;
                }

                if ($customerTelephone = trim($billingAddress->getTelephone())) {
                    $request['paymentMethod']['personalDetails']['telephoneNumber'] = $customerTelephone;
                }

                if ($firstName = $billingAddress->getFirstname()) {
                    $request['paymentMethod']['personalDetails']['firstName'] = $firstName;
                }

                if ($lastName = $billingAddress->getLastname()) {
                    $request['paymentMethod']['personalDetails']['lastName'] = $lastName;
                }
            } else {
                if ($customerEmail = $billingAddress->getEmail()) {
                    $request['shopperEmail'] = $customerEmail;
                }

                if ($customerTelephone = trim($billingAddress->getTelephone())) {
                    $request['telephoneNumber'] = $customerTelephone;
                }

                if ($firstName = $billingAddress->getFirstname()) {
                    $request['shopperName']['firstName'] = $firstName;
                }

                if ($lastName = $billingAddress->getLastname()) {
                    $request['shopperName']['lastName'] = $lastName;
                }
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
    public function buildCustomerIpData($request = [], $ipAddress)
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
    public function buildAddressData($request = [], $billingAddress, $shippingAddress)
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

            $address = $this->getStreetStringFromAddress($billingAddress);

            if (!empty($address["name"])) {
                $requestBilling["street"] = $address["name"];
            }

            if (!empty($address["house_number"])) {
                $requestBilling["houseNumberOrName"] = $address["house_number"];
            }

            if (!empty($billingAddress->getPostcode())) {
                $requestBilling["postalCode"] = $billingAddress->getPostcode();
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
            $address = $this->getStreetStringFromAddress($shippingAddress);

            if (!empty($address['name'])) {
                $requestDelivery["street"] = $address["name"];
            }

            if (!empty($address["house_number"])) {
                $requestDelivery["houseNumberOrName"] = $address["house_number"];
            }

            if (!empty($shippingAddress->getPostcode())) {
                $requestDelivery["postalCode"] = $shippingAddress->getPostcode();
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
     * @param $paymentMethod
     * @return array
     */
    public function buildPaymentData($request = [], $amount, $currencyCode, $reference, $paymentMethod)
    {
        $request['amount'] = [
            'currency' => $currencyCode,
            'value' => $this->adyenHelper->formatAmount($amount, $currencyCode)
        ];

        $request["reference"] = $reference;
        $request["fraudOffset"] = "0";

        return $request;
    }

    /**
     * @param array $request
     * @return array
     */
    public function buildBrowserData($request = [])
    {
        $request['browserInfo'] = [
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'acceptHeader' => $_SERVER['HTTP_ACCEPT']
        ];

        return $request;
    }

    /**
     * @param array $request
     * @param $payment
     * @param $store
     * @return array
     */
    public function buildThreeDS2Data($request = [], $additionalData)
    {
        $request['additionalData']['allow3DS2'] = true;
        $request['origin'] = $this->adyenHelper->getOrigin();
        $request['channel'] = 'web';
        $request['browserInfo']['screenWidth'] = $additionalData[AdyenCcDataAssignObserver::SCREEN_WIDTH];
        $request['browserInfo']['screenHeight'] = $additionalData[AdyenCcDataAssignObserver::SCREEN_HEIGHT];
        $request['browserInfo']['colorDepth'] = $additionalData[AdyenCcDataAssignObserver::SCREEN_COLOR_DEPTH];
        $request['browserInfo']['timeZoneOffset'] = $additionalData[AdyenCcDataAssignObserver::TIMEZONE_OFFSET];
        $request['browserInfo']['language'] = $additionalData[AdyenCcDataAssignObserver::LANGUAGE];

        if ($javaEnabled = $additionalData[AdyenCcDataAssignObserver::JAVA_ENABLED]) {
            $request['browserInfo']['javaEnabled'] = $javaEnabled;
        } else {
            $request['browserInfo']['javaEnabled'] = false;
        }
        return $request;
    }

    /**
     * @param $request
     * @param $areaCode
     * @param $storeId
     * @param $payment
     */
    public function buildRecurringData($request = [], $areaCode, int $storeId, $additionalData)
    {
        // If the vault feature is on this logic is handled in the VaultDataBuilder
        if (!$this->adyenHelper->isCreditCardVaultEnabled()) {

            if ($areaCode !== \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
                $storeId = null;
            }

            $enableOneclick = $this->adyenHelper->getAdyenAbstractConfigData('enable_oneclick', $storeId);
            $enableRecurring = $this->adyenHelper->getAdyenAbstractConfigData('enable_recurring', $storeId);

            if ($enableOneclick) {
                $request['enableOneClick'] = true;
            } else {
                $request['enableOneClick'] = false;
            }

            if ($enableRecurring) {
                $request['enableRecurring'] = true;
            } else {
                $request['enableRecurring'] = false;
            }

            // value can be 0,1 or true
            if (!empty($additionalData[AdyenCcDataAssignObserver::STORE_CC])) {
                $request['paymentMethod']['storeDetails'] = true;
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
    public function buildCCData($request = [], $payload, $storeId, $areaCode)
    {
        // If ccType is set use this. For bcmc you need bcmc otherwise it will fail

        if (!empty($payload['method']) && $payload['method'] == 'adyen_oneclick' &&
            !empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA]['variant'])
        ) {
            $request['paymentMethod']['type'] = $payload[PaymentInterface::KEY_ADDITIONAL_DATA]['variant'];
        } else {
            $request['paymentMethod']['type'] = 'scheme';
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER]) &&
            $cardNumber = $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER]) {
            $request['paymentMethod']['encryptedCardNumber'] = $cardNumber;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH]) &&
            $expiryMonth = $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH]) {
            $request['paymentMethod']['encryptedExpiryMonth'] = $expiryMonth;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR]) &&
            $expiryYear = $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR]) {
            $request['paymentMethod']['encryptedExpiryYear'] = $expiryYear;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::HOLDER_NAME]) && $holderName =
                $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::HOLDER_NAME]) {
            $request['paymentMethod']['holderName'] = $holderName;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE]) &&
            $securityCode = $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE]) {
            $request['paymentMethod']['encryptedSecurityCode'] = $securityCode;
        }

        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenOneclickDataAssignObserver::RECURRING_DETAIL_REFERENCE]) &&
            $recurringDetailReference = $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenOneclickDataAssignObserver::RECURRING_DETAIL_REFERENCE]
        ) {
            $request['paymentMethod']['recurringDetailReference'] = $recurringDetailReference;
        }

        // set customerInteraction
        $recurringContractType = $this->adyenHelper->getAdyenOneclickConfigData('recurring_payment_type');
        if (!empty($payload['method']) && $payload['method'] == 'adyen_oneclick'
            && $recurringContractType == \Adyen\Payment\Model\RecurringType::RECURRING) {
            $request['shopperInteraction'] = "ContAuth";
        } else {
            $request['shopperInteraction'] = "Ecommerce";
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
        if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS])) {
            if (($numberOfInstallment = $payload[PaymentInterface::KEY_ADDITIONAL_DATA][AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS]) > 0) {
                $request['installments']['value'] = $numberOfInstallment;
            }
        }

        return $request;
    }


    /**
     * @param $request
     * @param $additionalInformation
     * @return mixed
     */
    public function buildVaultData($request = [], $payload)
    {
        if ($this->adyenHelper->isCreditCardVaultEnabled()) {
            if (!empty($payload[PaymentInterface::KEY_ADDITIONAL_DATA][VaultConfigProvider::IS_ACTIVE_CODE]) &&
                $payload[PaymentInterface::KEY_ADDITIONAL_DATA][VaultConfigProvider::IS_ACTIVE_CODE] === true ||
                !empty($payload[VaultConfigProvider::IS_ACTIVE_CODE]) &&
                $payload[VaultConfigProvider::IS_ACTIVE_CODE] === true
            ) {
                // store it only as oneclick otherwise we store oneclick tokens (maestro+bcmc) that will fail
                $request['enableRecurring'] = true;
            } else {
                // explicity turn this off as merchants have recurring on by default
                $request['enableRecurring'] = false;
            }
        }

        return $request;
    }

    /**
     * The billing address retrieved from the Quote and the one retrieved from the Order has some differences
     * Therefore we need to check if the getStreetFull function exists and use that if yes, otherwise use the more
     * commont getStreetLine1
     *
     * @param $billingAddress
     * @return array
     */
    private function getStreetStringFromAddress($address)
    {
        if (method_exists($address, 'getStreetFull')) {
            // Parse address into street and house number where possible
            $address = $this->adyenHelper->getStreetFromString($address->getStreetFull());
        } else {
            $address = $this->adyenHelper->getStreetFromString(implode(' ', [$address->getStreetLine1(), $address->getStreetLine2()]));
        }

        return $address;
    }

    /**
     * Only adds idempotency key if payment method is adyen_hpp for now
     *
     * @param array $request
     * @param $paymentMethod
     * @param $idempotencyKey
     * @return array
     */
    public function addIdempotencyKey($request = [], $paymentMethod, $idempotencyKey)
    {
        if (!empty($paymentMethod) && $paymentMethod == 'adyen_hpp') {
            $request['idempotencyKey'] = $idempotencyKey;
        }

        return $request;
    }
}

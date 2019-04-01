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
//TODO: enable stateOrProvince field if no issues with empty values
class Requests extends AbstractHelper
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

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
     * @param $request
     * @param int $customerId
     * @param $billingAddress
     * @return mixed
     */
    public function buildCustomerData($request = [], $customerId = 0, $billingAddress, \Magento\Quote\Model\Quote\Payment $payment)
    {
        if ($customerId > 0) {
            $request['shopperReference'] = $customerId;
        }

        if (!empty($billingAddress)) {
            // Openinvoice and afterpayTouch methods requires different request format
            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod(
                    $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
                ) && !$this->adyenHelper->isPaymentMethodAfterpayTouchMethod(
                    $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
                )
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
//                "stateOrProvince" => '',
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

//            if (!empty($billingAddress->getRegionCode())) {
//                $requestBilling["stateOrProvince"] = $billingAddress->getRegionCode();
//            }

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
//                "stateOrProvince" => '',
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

//            if (!empty($shippingAddress->getRegionCode())) {
//                $requestDelivery["stateOrProvince"] = $shippingAddress->getRegionCode();
//            }

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
     * @param $request
     * @param $amount
     * @param $currencyCode
     * @param $reference
     * @return mixed
     */
    public function buildPaymentData($request = [], $amount, $currencyCode, $reference)
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
    public function buildThreeDS2Data($request = [], \Magento\Quote\Model\Quote\Payment $payment, $store)
    {
        $request['additionalData']['allow3DS2'] = true;
        $request['origin'] = $this->adyenHelper->getOrigin();
        $request['channel'] = 'web';
        $request['browserInfo']['screenWidth'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::SCREEN_WIDTH);
        $request['browserInfo']['screenHeight'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::SCREEN_HEIGHT);
        $request['browserInfo']['colorDepth'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::SCREEN_COLOR_DEPTH);
        $request['browserInfo']['timeZoneOffset'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::TIMEZONE_OFFSET);
        $request['browserInfo']['language'] = "nl-NL";//$this->adyenHelper->getCurrentLocaleCode($store); TODO change format to nl-NL instead of nl_NL
        $request['browserInfo']['javaEnabled'] = false; //$payment->getAdditionalInformation(AdyenCcDataAssignObserver::JAVA_ENABLED);TODO make sure it is not passed as null

        // uset browser related data from additional information
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::SCREEN_WIDTH);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::SCREEN_HEIGHT);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::SCREEN_COLOR_DEPTH);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::TIMEZONE_OFFSET);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::JAVA_ENABLED);

        return $request;
    }

    /**
     * @param $request
     * @param $areaCode
     * @param $storeId
     * @param $payment
     */
    public function buildRecurringData($request = [], $areaCode, int $storeId, \Magento\Quote\Model\Quote\Payment $payment)
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

            if ($payment->getAdditionalInformation('store_cc') === '1') {
                $request['paymentMethod']['storeDetails'] = true;
            }
        }

        return $request;
    }

    /**
     * @param $request
     * @param $payment
     * @param $storeId
     * @return mixed
     */
    public function buildCCData($request = [], \Magento\Quote\Model\Quote\Payment $payment, $storeId, $areaCode)
    {
        // If ccType is set use this. For bcmc you need bcmc otherwise it will fail
        $request['paymentMethod']['type'] = "scheme";

        if ($cardNumber = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER)) {
            $request['paymentMethod']['encryptedCardNumber'] = $cardNumber;
        }

        if ($expiryMonth = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH)) {
            $request['paymentMethod']['encryptedExpiryMonth'] = $expiryMonth;
        }

        if ($expiryYear = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR)) {
            $request['paymentMethod']['encryptedExpiryYear'] = $expiryYear;
        }

        if ($holderName = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::HOLDER_NAME)) {
            $request['paymentMethod']['holderName'] = $holderName;
        }

        if ($securityCode = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE)) {
            $request['paymentMethod']['encryptedSecurityCode'] = $securityCode;
        }

        if ($recurringDetailReference = $payment->getAdditionalInformation(AdyenOneclickDataAssignObserver::RECURRING_DETAIL_REFERENCE)) {
            $request['paymentMethod']['recurringDetailReference'] = $recurringDetailReference;
        }

        // Remove from additional information
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::HOLDER_NAME);

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
        if ($payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) > 0) {
            $request['installments']['value'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS);
        }

        return $request;
    }


    /**
     * @param $request
     * @param $additionalInformation
     * @return mixed
     */
    public function buildVaultData($request = [], $additionalInformation)
    {
        if (!empty($additionalInformation[VaultConfigProvider::IS_ACTIVE_CODE]) &&
            $additionalInformation[VaultConfigProvider::IS_ACTIVE_CODE] === true
        ) {
            // store it only as oneclick otherwise we store oneclick tokens (maestro+bcmc) that will fail
            $request['enableRecurring'] = true;
        } else {
            // explicity turn this off as merchants have recurring on by default
            $request['enableRecurring'] = false;
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
            $address = $this->adyenHelper->getStreetFromString($address->getStreetLine1());
        }

        return $address;
    }
}

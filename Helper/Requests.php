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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Observer\AdyenOneclickDataAssignObserver;
use Adyen\Util\Uuid;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\InfoInterface;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class Requests extends AbstractHelper
{
    CONST MERCHANT_ACCOUNT = 'merchantAccount';

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var Config
     */
    private $adyenConfig;

    /**
     * @var UrlInterface
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
     * @param UrlInterface $urlBuilder
     * @param Address $addressHelper
     */
    public function __construct(
        Data $adyenHelper,
        Config $adyenConfig,
        UrlInterface $urlBuilder,
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
        $request[self::MERCHANT_ACCOUNT] = $merchantAccount;

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

            $houseNumberStreetLine = $this->adyenHelper->getAdyenAbstractConfigData(
                Config::XML_HOUSE_NUMBER_STREET_LINE,
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
        $stateData = $payment->getAdditionalInformation('stateData');

        if ($payment->getMethod() === AdyenPayByLinkConfigProvider::CODE) {
            $request['storePaymentMethodMode'] = 'askForConsent';
        } else {
            $request['storePaymentMethod'] = (bool)($stateData['storePaymentMethod'] ?? $storedPaymentMethodsEnabled);
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

    public function buildDonationData($buildSubject, $storeId): array
    {
        return [
            'amount' => $buildSubject['amount'],
            'reference' => Uuid::generateV4(),
            'shopperReference' => $buildSubject['shopperReference'],
            'paymentMethod' => $buildSubject['paymentMethod'],
            'donationToken' => $buildSubject['donationToken'],
            'donationOriginalPspReference' => $buildSubject['donationOriginalPspReference'],
            'donationAccount' => $this->adyenConfig->getCharityMerchantAccount($storeId),
            'returnUrl' => $buildSubject['returnUrl'],
            'merchantAccount' => $this->adyenHelper->getAdyenMerchantAccount('adyen_giving', $storeId),
            'shopperInteraction' => 'Ecommerce'
        ];
    }
}

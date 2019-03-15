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

namespace Adyen\Payment\Plugin;

use Adyen\Payment\Observer\AdyenHppDataAssignObserver;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Magento\Vault\Model\Ui\VaultConfigProvider;

class PaymentInformationManagement
{
    private $checkoutSession;
    private $adyenHelper;
    private $context;
    private $transferFactory;
    private $transactionPayment;
    private $checkoutResponseValidator;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Model\Context $context,
        \Adyen\Payment\Gateway\Http\TransferFactory $transferFactory,
        \Adyen\Payment\Gateway\Http\Client\TransactionPayment $transactionPayment,
        \Adyen\Payment\Gateway\Validator\CheckoutResponseValidator $checkoutResponseValidator
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->context = $context;
        $this->transferFactory = $transferFactory;
        $this->transactionPayment = $transactionPayment;
        $this->checkoutResponseValidator = $checkoutResponseValidator;
    }

    /**
     * @param \Magento\Checkout\Model\PaymentInformationManagement $subject
     * @param $response
     */
    public function afterSavePaymentInformation(
        \Magento\Checkout\Model\PaymentInformationManagement $subject,
        $response
    )
    {
        $quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();

        // Build request for first payments call
        $request = [];

        // Merchant account data builder
        $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($payment->getMethod(), $quote->getStoreId());
        $request["merchantAccount"] = $merchantAccount;

        // Customer data builder
        $customerId = $quote->getCustomerId();

        if ($customerId > 0) {
            $request['shopperReference'] = $customerId;
        }

        $billingAddress = $quote->getBillingAddress();

        if (!empty($billingAddress)) {
            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod(
                    $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
                ) && !$this->adyenHelper->isPaymentMethodAfterpayTouchMethod(
                    $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE)
                )) {
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

        // Customer Ip data builder

        $request['shopperIP'] = $quote->getRemoteIp();

        // AddressDataBuilder
        if ($billingAddress) {

            $requestBilling = [
                "street" => "N/A",
                "postalCode" => '',
                "city" => "N/A",
                "houseNumberOrName" => '',
                "stateOrProvince" => '',
                "country" => "ZZ"
            ];

            $address = $this->adyenHelper->getStreetFromString($billingAddress->getStreetFull());

            if ($address["name"]) {
                $requestBilling["street"] = $address["name"];
            }

            if ($address["house_number"]) {
                $requestBilling["houseNumberOrName"] = $address["house_number"];
            }

            if ($billingAddress->getPostcode()) {
                $requestBilling["postalCode"] = $billingAddress->getPostcode();
            }

            if ($billingAddress->getCity()) {
                $requestBilling["city"] = $billingAddress->getCity();
            }

            if ($billingAddress->getRegionCode()) {
                $requestBilling["stateOrProvince"] = $billingAddress->getRegionCode();
            }

            if ($billingAddress->getCountryId()) {
                $requestBilling["country"] = $billingAddress->getCountryId();
            }

            $request['billingAddress'] = $requestBilling;
        }

        $shippingAddress = $quote->getShippingAddress();

        if ($shippingAddress) {

            $requestDeliveryDefaults = [
                "street" => "N/A",
                "postalCode" => '',
                "city" => "N/A",
                "houseNumberOrName" => '',
                "stateOrProvince" => '',
                "country" => "ZZ"
            ];

            $requestDelivery = $requestDeliveryDefaults;

            $address = $this->adyenHelper->getStreetFromString($shippingAddress->getStreetFull());

            if ($address['name']) {
                $requestDelivery["street"] = $address["name"];
            }

            if ($address["house_number"]) {
                $requestDelivery["houseNumberOrName"] = $address["house_number"];
            }

            if ($shippingAddress->getPostcode()) {
                $requestDelivery["postalCode"] = $shippingAddress->getPostcode();
            }

            if ($shippingAddress->getCity()) {
                $requestDelivery["city"] = $shippingAddress->getCity();
            }

            if ($shippingAddress->getRegionCode()) {
                $requestDelivery["stateOrProvince"] = $shippingAddress->getRegionCode();
            }

            if ($shippingAddress->getCountryId()) {
                $requestDelivery["country"] = $shippingAddress->getCountryId();
            }

            // If nothing is changed which means delivery address is not filled
            if ($requestDelivery !== $requestDeliveryDefaults) {
                $request['deliveryAddress'] = $requestDelivery;
            }
        }

        // PaymentDataBuilder

        $currencyCode = $quote->getQuoteCurrencyCode();
        $amount = $quote->getGrandTotal();

        $amount = ['currency' => $currencyCode,
            'value' => $this->adyenHelper->formatAmount($amount, $currencyCode)];

        $request["amount"] = $amount;
        $request["reference"] = $quote->getId();
        $request["fraudOffset"] = "0";

        // Browser data builder

        $request['browserInfo'] = [
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'acceptHeader' => $_SERVER['HTTP_ACCEPT']
        ];

        // 3DS2.0 data builder
        //TODO PW-1106
        $is3DS2Enabled = true;
        if ($is3DS2Enabled) {
            $request['origin'] = $this->adyenHelper->getOrigin();
            $request['additionalData']['allow3DS2'] = true;
            $request['channel'] = 'web';
            $request['browserInfo'] = [
                'screenWidth' => $payment->getAdditionalInformation(AdyenCcDataAssignObserver::SCREEN_WIDTH),
                'screenHeight' => $payment->getAdditionalInformation(AdyenCcDataAssignObserver::SCREEN_HEIGHT),
                'colorDepth' => $payment->getAdditionalInformation(AdyenCcDataAssignObserver::SCREEN_COLOR_DEPTH),
                'timeZoneOffset' => $payment->getAdditionalInformation(AdyenCcDataAssignObserver::TIMEZONE_OFFSET),
                'language' => $this->adyenHelper->getCurrentLocaleCode($quote->getStore()),
                'javaEnabled' => $payment->getAdditionalInformation(AdyenCcDataAssignObserver::JAVA_ENABLED)
            ];
        }

        // RecurringDataBuilder

        // If the vault feature is on this logic is handled in the VaultDataBuilder
        if (!$this->adyenHelper->isCreditCardVaultEnabled()) {

            $storeId = null;
            if ($this->context->getAppState()->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
                $storeId = $quote->getStoreId();
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

        // CcAuthorizationDataBuilder

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

        // Remove from additional data
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::HOLDER_NAME);

        /**
         * if MOTO for backend is enabled use MOTO as shopper interaction type
         */
        $enableMoto = $this->adyenHelper->getAdyenCcConfigDataFlag('enable_moto', $storeId);
        if ($this->context->getAppState()->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE &&
            $enableMoto
        ) {
            $request['shopperInteraction'] = "Moto";
        }
        // if installments is set add it into the request
        if ($payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) &&
            $payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) > 0
        ) {
            $request['installments']['value'] = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS);
        }

        // Valut data builder

        $data = $payment->getAdditionalInformation();

        if (!empty($data[VaultConfigProvider::IS_ACTIVE_CODE]) &&
            $data[VaultConfigProvider::IS_ACTIVE_CODE] === true
        ) {
            // store it only as oneclick otherwise we store oneclick tokens (maestro+bcmc) that will fail
            $request['enableRecurring'] = true;
        } else {
            // explicity turn this off as merchants have recurring on by default
            $request['enableRecurring'] = false;
        }

        $transferObject = $this->transferFactory->create($request);
        $response_a = $this->transactionPayment->placeRequest($transferObject);
        $this->checkoutResponseValidator->validate($response_a);

        return json_encode(array("test" => $response, "addData" => $response_a));
    }
}

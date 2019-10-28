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

namespace Adyen\Payment\Model;

use \Adyen\Payment\Api\AdyenPaymentProcessInterface;

class AdyenPaymentProcess implements AdyenPaymentProcessInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Adyen\Payment\Helper\Requests
     */
    private $adyenRequestHelper;

    /**
     * @var \Magento\Framework\Model\Context
     */
    private $context;

    /**
     * @var \Adyen\Payment\Gateway\Http\TransferFactory
     */
    private $transferFactory;

    /**
     * @var \Adyen\Payment\Gateway\Http\Client\TransactionPayment
     */
    private $transactionPayment;

    /**
     * @var \Adyen\Payment\Gateway\Validator\CheckoutResponseValidator
     */
    private $checkoutResponseValidator;

    /**
     * @var \Adyen\Payment\Gateway\Validator\ThreeDS2ResponseValidator
     */
    private $threeDS2ResponseValidator;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * AdyenPaymentProcess constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Helper\Requests $adyenRequestHelper
     * @param \Adyen\Payment\Gateway\Http\TransferFactory $transferFactory
     * @param \Adyen\Payment\Gateway\Http\Client\TransactionPayment $transactionPayment
     * @param \Adyen\Payment\Gateway\Validator\CheckoutResponseValidator $checkoutResponseValidator
     * @param \Adyen\Payment\Gateway\Validator\ThreeDS2ResponseValidator $threeDS2ResponseValidator
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Helper\Requests $adyenRequestHelper,
        \Adyen\Payment\Gateway\Http\TransferFactory $transferFactory,
        \Adyen\Payment\Gateway\Http\Client\TransactionPayment $transactionPayment,
        \Adyen\Payment\Gateway\Validator\CheckoutResponseValidator $checkoutResponseValidator,
        \Adyen\Payment\Gateway\Validator\ThreeDS2ResponseValidator $threeDS2ResponseValidator,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    )
    {
        $this->context = $context;
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->adyenRequestHelper = $adyenRequestHelper;
        $this->transferFactory = $transferFactory;
        $this->transactionPayment = $transactionPayment;
        $this->checkoutResponseValidator = $checkoutResponseValidator;
        $this->threeDS2ResponseValidator = $threeDS2ResponseValidator;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @api
     * @param string $payload
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initiate($payload)
    {
        // Decode payload from frontend
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Error with payment method please select different payment method.'));
        }

        // Get payment and cart information from session
        $quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();

        // Init request array
        $requestBody = [];

        // Merchant account data builder
        $paymentMethod = $payment->getMethod();
        $storeId = $quote->getStoreId();
        $requestBody = $this->adyenRequestHelper->buildMerchantAccountData($requestBody, $paymentMethod, $storeId);

        // Customer data builder
        $customerId = $quote->getCustomerId();
        $billingAddress = $quote->getBillingAddress();
        $requestBody = $this->adyenRequestHelper->buildCustomerData($requestBody, $customerId, $billingAddress, $storeId, null, $payload);

        // Customer Ip data builder
        $shopperIp = $quote->getXForwardedFor();
        $requestBody = $this->adyenRequestHelper->buildCustomerIpData($requestBody, $shopperIp);

        // AddressDataBuilder
        $shippingAddress = $quote->getShippingAddress();
        $requestBody = $this->adyenRequestHelper->buildAddressData($requestBody, $billingAddress, $shippingAddress);

        // PaymentDataBuilder
        $currencyCode = $quote->getQuoteCurrencyCode();
        $amount = $quote->getGrandTotal();

        // Setting the orderid to null, so that we generate a new one for each /payments call
        $quote->setReservedOrderId(null);
        $reference = $quote->reserveOrderId()->getReservedOrderId();
      
        $this->adyenLogger->addAdyenDebug("CC payment started for order: " . $reference);

        $requestBody = $this->adyenRequestHelper->buildPaymentData($requestBody, $amount, $currencyCode, $reference, $paymentMethod);

        // Browser data builder
        $requestBody = $this->adyenRequestHelper->buildBrowserData($requestBody);

        // 3DS2.0 data builder
        $isThreeDS2Enabled = $this->adyenHelper->isCreditCardThreeDS2Enabled($storeId);
        if ($isThreeDS2Enabled) {
            $requestBody = $this->adyenRequestHelper->buildThreeDS2Data($requestBody, $payload, $quote->getStore());
        }

        // RecurringDataBuilder
        $areaCode = $this->context->getAppState()->getAreaCode();
        $requestBody = $this->adyenRequestHelper->buildRecurringData($requestBody, $areaCode, $storeId, $payload);

        // CcAuthorizationDataBuilder
        $requestBody = $this->adyenRequestHelper->buildCCData($requestBody, $payload, $storeId, $areaCode);

        // Vault data builder
        $requestBody = $this->adyenRequestHelper->buildVaultData($requestBody, $payload);

        // Add idempotency key if applicable
        $requestHeaders = $this->adyenRequestHelper->addIdempotencyKey([], $paymentMethod, $reference);

        $request['body'] = $requestBody;
        $request['headers'] = $requestHeaders;

        // Create and send request
        $transferObject = $this->transferFactory->create($request);
        $paymentsResponse = $this->transactionPayment->placeRequest($transferObject);

        // Check if 3DS2.0 validation is needed or not
        // In case 3DS2.0 validation is necessary send the type and token back to the frontend
        if (!empty($paymentsResponse['resultCode'])) {
            if ($paymentsResponse['resultCode'] == 'IdentifyShopper' ||
                $paymentsResponse['resultCode'] == 'ChallengeShopper') {
                if ($this->threeDS2ResponseValidator->validate(array(
                    "response" => $paymentsResponse,
                    "payment" => $payment
                ))->isValid()) {
                    $quote->save();
                    return $this->adyenHelper->buildThreeDS2ProcessResponseJson($payment->getAdditionalInformation('threeDS2Type'),
                        $payment->getAdditionalInformation('threeDS2Token'));
                }
            }
        } else {
            $errorMsg = __('Error with payment method please select different payment method.');
            throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
        }

        // Save the payments response because we are going to need it during the place order flow
        $payment->setAdditionalInformation("paymentsResponse", $paymentsResponse);

        // To actually save the additional info changes into the quote
        $quote->save();

        $this->adyenLogger->addAdyenDebug("CC payment finished for order: " . $quote->getReservedOrderId());
        if (!empty($paymentsResponse['resultCode'])) {
            $this->adyenLogger->addAdyenDebug('Result code: ' . $paymentsResponse['resultCode']);
        }

        // Original flow can continue, return to frontend and place the order
        return $this->adyenHelper->buildThreeDS2ProcessResponseJson();
    }
}

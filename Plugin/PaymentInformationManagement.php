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
     * PaymentInformationManagement constructor.
     *
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Helper\Requests $adyenRequestHelper
     * @param \Magento\Framework\Model\Context $context
     * @param \Adyen\Payment\Gateway\Http\TransferFactory $transferFactory
     * @param \Adyen\Payment\Gateway\Http\Client\TransactionPayment $transactionPayment
     * @param \Adyen\Payment\Gateway\Validator\CheckoutResponseValidator $checkoutResponseValidator
     * @param \Adyen\Payment\Gateway\Validator\ThreeDS2ResponseValidator $threeDS2ResponseValidator
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Helper\Requests $adyenRequestHelper,
        \Magento\Framework\Model\Context $context,
        \Adyen\Payment\Gateway\Http\TransferFactory $transferFactory,
        \Adyen\Payment\Gateway\Http\Client\TransactionPayment $transactionPayment,
        \Adyen\Payment\Gateway\Validator\CheckoutResponseValidator $checkoutResponseValidator,
        \Adyen\Payment\Gateway\Validator\ThreeDS2ResponseValidator $threeDS2ResponseValidator
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->adyenRequestHelper = $adyenRequestHelper;
        $this->context = $context;
        $this->transferFactory = $transferFactory;
        $this->transactionPayment = $transactionPayment;
        $this->checkoutResponseValidator = $checkoutResponseValidator;
        $this->threeDS2ResponseValidator = $threeDS2ResponseValidator;
    }

    /**
     * @param \Magento\Checkout\Model\PaymentInformationManagement $subject
     * @param $response
     */
    public function afterSavePaymentInformation(
        \Magento\Checkout\Model\PaymentInformationManagement $subject,
        $response
    ) {

        // Get payment and cart information from session
        $quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();

        // in case payments response is already there we don't need to perform another payments call
        // we indicate it with the placeOrder additionalInformation
        if ($payment->getAdditionalInformation('placeOrder')) {
            $payment->unsAdditionalInformation('placeOrder');
            $quote->save();

            return $this->adyenHelper->buildThreeDS2ProcessResponseJson();
        }
        if (strpos($payment->getMethod(), "adyen_cc") !== 0 &&
            strpos($payment->getMethod(), "adyen_oneclick") !== 0) {
            return $response;
        }
        // Init request array
        $request = [];

        // Merchant account data builder
        $paymentMethod = $payment->getMethod();
        $storeId = $quote->getStoreId();
        $request = $this->adyenRequestHelper->buildMerchantAccountData($request, $paymentMethod, $storeId);

        // Customer data builder
        $customerId = $quote->getCustomerId();
        $billingAddress = $quote->getBillingAddress();
        $request = $this->adyenRequestHelper->buildCustomerData($request, $customerId, $billingAddress, $payment);

        // Customer Ip data builder
        $shopperIp = $quote->getRemoteIp();
        $request = $this->adyenRequestHelper->buildCustomerIpData($request, $shopperIp);

        // AddressDataBuilder
        $shippingAddress = $quote->getShippingAddress();
        $request = $this->adyenRequestHelper->buildAddressData($request, $billingAddress, $shippingAddress);

        // PaymentDataBuilder
        $currencyCode = $quote->getQuoteCurrencyCode();
        $amount = $quote->getGrandTotal();
        // Setting the orderid to null, so that we generate a new one for each /payments call
        $quote->setReservedOrderId(null);
        $reference = $quote->reserveOrderId()->getReservedOrderId();
        $request = $this->adyenRequestHelper->buildPaymentData($request, $amount, $currencyCode, $reference);

        // Browser data builder
        $request = $this->adyenRequestHelper->buildBrowserData($request);

        // 3DS2.0 data builder
        $isThreeDS2Enabled = $this->adyenHelper->isCreditCardThreeDS2Enabled($storeId);
        if ($isThreeDS2Enabled) {
            $request = $this->adyenRequestHelper->buildThreeDS2Data($request, $payment, $quote->getStore());
        }

        // RecurringDataBuilder
        $areaCode = $this->context->getAppState()->getAreaCode();
        $request = $this->adyenRequestHelper->buildRecurringData($request, $areaCode, $storeId, $payment);

        // CcAuthorizationDataBuilder
        $request = $this->adyenRequestHelper->buildCCData($request, $payment, $storeId, $areaCode);

        // Valut data builder
        $request = $this->adyenRequestHelper->buildVaultData($request, $payment->getAdditionalInformation());

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

        // Setting the placeOrder to true enables the process to skip the payments call because the paymentsResponse
        // is already in place - only set placeOrder to true when you have the paymentsResponse
        $payment->setAdditionalInformation('placeOrder', true);

        // To actually save the additional info changes into the quote
        $quote->save();

        // Original flow can continue, return to frontend and place the order
        return $this->adyenHelper->buildThreeDS2ProcessResponseJson();
    }
}

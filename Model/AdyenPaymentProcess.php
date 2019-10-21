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

    private $quoteRepo;
    private $qutoeMaskFactory;


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
        \Magento\Quote\Model\QuoteIdMaskFactory $qutoeMaskFactory,
        \Magento\Quote\Model\QuoteRepository $quoteRepo
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
        $this->quoteRepo = $quoteRepo;
        $this->quoteMaskFactory = $qutoeMaskFactory;
    }

    /**
     * @api
     * @param string $payload
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initiate($payload)
    {
        // When payload is not an array then why assume its a jsonstring so we try to decode 
        if(!is_array($payload)){
            $payload = json_decode($payload, true);
            // Validate JSON that has just been parsed if it was in a valid format
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Magento\Framework\Exception\LocalizedException('Error with payment method please select different payment method.');
            }
        }
        $quoteId = $payload['quote_id'];
        //if the quoteId is not an nummeric value then we assume that its a maked quote id from a guest card 
        if(!is_numeric($quoteId)){
            $maskedQuote = $this->quoteMaskFactory->create()->load($quoteId, 'masked_id');
            $quoteId =  $maskedQuote->getQuoteId();
        } 
        $quote = $this->quoteRepo->get($quoteId);

        // Get payment and cart information from session
        //$quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();

        // Init request array
        $request = [];

        // Merchant account data builder
        $paymentMethod = $payment->getMethod();
        $storeId = $quote->getStoreId();
        $request = $this->adyenRequestHelper->buildMerchantAccountData($request, $paymentMethod, $storeId);

        // Customer data builder
        $customerId = $quote->getCustomerId();
        $billingAddress = $quote->getBillingAddress();
        $request = $this->adyenRequestHelper->buildCustomerData($request, $customerId, $billingAddress, $storeId, null, $payload);

        // Customer Ip data builder
        $shopperIp = $quote->getXForwardedFor();
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
            $request = $this->adyenRequestHelper->buildThreeDS2Data($request, $payload, $quote->getStore());
        }

        // RecurringDataBuilder
        $areaCode = $this->context->getAppState()->getAreaCode();
        $request = $this->adyenRequestHelper->buildRecurringData($request, $areaCode, $storeId, $payload);

        // CcAuthorizationDataBuilder
        $request = $this->adyenRequestHelper->buildCCData($request, $payload, $storeId, $areaCode);

        // Vault data builder
        $request = $this->adyenRequestHelper->buildVaultData($request, $payload);

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
            $errorMsg = $paymentsResponse['error'];
            throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
        }

        // Save the payments response because we are going to need it during the place order flow
        $payment->setAdditionalInformation("paymentsResponse", $paymentsResponse);

        // To actually save the additional info changes into the quote
        $quote->save();

        // Original flow can continue, return to frontend and place the order
        return $this->adyenHelper->buildThreeDS2ProcessResponseJson();
    }
}

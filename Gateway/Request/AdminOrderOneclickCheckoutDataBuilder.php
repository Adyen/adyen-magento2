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
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\StateData;
use Magento\Catalog\Helper\Image;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class AdminOrderOneclickCheckoutDataBuilder implements BuilderInterface
{



    /**
     * AdminOrderOneclickCheckoutDataBuilder constructor.
     *
     */
    public function __construct(

    ) {

    }

    /**
     * @param array $buildSubject
     * @return mixed
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        $requestBody = array(
            "amount" => array(
                "currency" => "EUR",
                "value" => 50000
            ),
            "reference" => "000000076",
            "paymentMethod" => array(
                "type" => "scheme",
                "storedPaymentMethodId" => "8416582371077032"
            ),
            "returnUrl" => "https://adyen.com/",
            "shopperInteraction" => "ContAuth",
            "recurringProcessingModel" => "Subscription",
            "merchantAccount" => "RokLedinski",
            "shopperReference" => "002"
        );

        return [
            'body' => $requestBody
        ];

        // build params that are being fetched from stateData in the CheckoutDataBuilder
        // shopperReference is being returned in the CustomerDataBuilder as the $customerId
        // storedPaymentMethodId is being returned (nowhere yet?))

        // Initialize the request body with the current state data
        // Multishipping checkout uses the cc_number field for state data

        // we don't have stateData in the admin orders, as there is no component to fetch it from
        // question: where should I search for paymentMethod object?
        // --------------------------------------
        // Initial payment that creates a token: example: "paymentMethod": {          ==>
        // "paymentMethod":{
        //      "type":"scheme",
        //      "number":"4111111111111111",
        //      "expiryMonth":"10",
        //      "expiryYear":"2020",
        //      "cvc":"737",
        //      "holderName":"John Smith"
        //   },
        //   "reference":"YOUR_ORDER_NUMBER",
        //   "shopperInteraction": "Ecommerce",
        //   "recurringProcessingModel": "Subscription",
        //   "merchantAccount":"YOUR_MERCHANT_ACCOUNT",
        //   "shopperReference":"YOUR_UNIQUE_SHOPPER_ID_IOfW3k9G2PvXFu2j",
        //   "returnUrl":"https://your-company.com/..."
        //}'
        // --------------------------------------
        //

        // How tokenization works:
        // - you can use the same token that were created with recurringProcessingModel: CardOnFile for subsequent Subscription payments
        // - in the initial payment, the token is created. A reference for the token is included in RECURRING_CONTRACT notification ==> additionalData.recurring.recurringDetailReference
        // - upon enabling Recurring details in Additional data in CA this is also returned in the synchronous flow
        // - tokens involved in making a payment with stored details are stored in the vault_payment_token database table
        // - storedPaymentMethodId -> gateway_token
        // - shopperReference -> customer_id

        // Example of a recurring Subscription payment:
        // --------------------------------------
        // "amount":{
        //      "value":2000,
        //      "currency":"USD"
        //   },
        //   "paymentMethod":{
        //      "type":"scheme",
        //      "storedPaymentMethodId":"7219687191761347"                  ==> gateway_token in vault_payment_token table, reference_id in paypal_billing_agreement
        //   },
        //   "reference":"YOUR_ORDER_NUMBER",
        //   "shopperInteraction": "ContAuth",
        //   "recurringProcessingModel": "Subscription",
        //   "merchantAccount":"YOUR_MERCHANT_ACCOUNT",
        //   "shopperReference":"YOUR_UNIQUE_SHOPPER_ID_IOfW3k9G2PvXFu2j"   ==> customer_id in vault_payment_token table
        //}'
        // --------------------------------------

        // the question is are we storing the tokens for subscription initial payments -> in the vault_payment_token, I only have the tokens I stored with the CardOnFile configured in M2 admin panel
        // but I have the "subscription" token stored somewhere as it is fetched and displayed in the admin orders page, so where is it? paypal_billing_agreement table?


        // how do we build the request in this builder? do we add all the params needed in the body of the request in this builder?

        // so stateData also has a quote? or are we getting this information from the quote_payment table?

    }
}
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

use Adyen\Payment\Api\AdyenThreeDS2ProcessInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;

class AdyenThreeDS2Process implements AdyenThreeDS2ProcessInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var Vault
     */
    private $vaultHelper;

    /**
     * @var Quote
     */
    private $quoteHelper;

    /**
     * AdyenThreeDS2Process constructor.
     *
     * @param Session $checkoutSession
     * @param Data $adyenHelper
     * @param OrderFactory $orderFactory
     * @param AdyenLogger $adyenLogger
     * @param Vault $vaultHelper
     * @param Quote $quoteHelper
     */
    public function __construct(
        Session $checkoutSession,
        Data $adyenHelper,
        OrderFactory $orderFactory,
        AdyenLogger $adyenLogger,
        Vault $vaultHelper,
        Quote $quoteHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->orderFactory = $orderFactory;
        $this->adyenLogger = $adyenLogger;
        $this->vaultHelper = $vaultHelper;
        $this->quoteHelper = $quoteHelper;
    }

    /**
     * @param string $payload
     * @return string
     * @api
     */
    public function initiate($payload)
    {
        // Decode payload from frontend
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('3D secure 2.0 failed because the request was not a valid JSON')
            );
        }

        if (empty($payload['orderId'])) {
            $order = $this->getOrder();
            // In the next major release remove support for retrieving order from session and throw exception instead
            //throw new \Magento\Framework\Exception\LocalizedException
            //(__('3D secure 2.0 failed because of a missing order id'));
        } else {
            // Create order by order id
            $order = $this->orderFactory->create()->load($payload['orderId']);
            // don't send orderId to adyen. Improve that orderId and state.data are separated in payload
            unset($payload['orderId']);
        }

        $payment = $order->getPayment();

        // Init payments/details request
        $result = [];

        if ($paymentData = $payment->getAdditionalInformation("adyenPaymentData")) {
            // Add payment data into the request object
            $request = [
                "paymentData" => $paymentData
            ];

            // unset payment data from additional information
            $payment->unsAdditionalInformation("adyenPaymentData");
        } else {
            $this->adyenLogger->error("3D secure 2.0 failed, payment data not found");
            throw new \Magento\Framework\Exception\LocalizedException(
                __('3D secure 2.0 failed, payment data not found')
            );
        }

        // Depends on the component's response we send a fingerprint or the challenge result
        if (!empty($payload['details']['threeds2.fingerprint'])) {
            $request['details']['threeds2.fingerprint'] = $payload['details']['threeds2.fingerprint'];
        } elseif (!empty($payload['details']['threeds2.challengeResult'])) {
            $request['details']['threeds2.challengeResult'] = $payload['details']['threeds2.challengeResult'];
        } elseif (!empty($payload)) {
            $request = $payload;
        }


        // Send the request
        try {
            $client = $this->adyenHelper->initializeAdyenClient($order->getStoreId());
            $service = $this->adyenHelper->createAdyenCheckoutService($client);

            $result = $service->paymentsDetails($request);
        } catch (\Adyen\AdyenException $e) {
            $this->adyenLogger->error("3D secure 2.0 failed" . $e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__('3D secure 2.0 failed'));
        }

        // Check if result is challenge shopper, if yes return the token
        if (!empty($result['resultCode']) &&
            $result['resultCode'] === 'ChallengeShopper' &&
            !empty($result['authentication']['threeds2.challengeToken'])
        ) {
            return $this->adyenHelper->buildThreeDS2ProcessResponseJson(
                $result['resultCode'],
                $result['authentication']['threeds2.challengeToken']
            );
        }
        //Fallback for 3DS in case of redirect
        if (!empty($result['resultCode']) &&
            $result['resultCode'] === 'RedirectShopper'
        ) {
            $response['type'] =  $result['resultCode'];
            $response['action']= $result['action'];

            return json_encode($response);
        }

        // Save the payments response because we are going to need it during the place order flow
        $payment->setAdditionalInformation("paymentsResponse", $result);

        if (!empty($result['additionalData'])) {
            $this->vaultHelper->saveRecurringDetails($payment, $result['additionalData']);
        }

        // To actually save the additional info changes into the quote
        $order->save();

        $response = [];

        if ($result['resultCode'] != 'Authorised') {

            //If the customer is guest don't attempt to replace the quote as the generated cart ID can't be used
            //in the frontend. Restore the previous quote instead
            if ($order->getCustomerIsGuest()){
                $this->checkoutSession->restoreQuote();
            } else {
                try {
                    $newQuote = $this->quoteHelper->cloneQuote($this->checkoutSession->getQuote(), $order);
                    $this->checkoutSession->replaceQuote($newQuote);
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->checkoutSession->restoreQuote();
                    $this->adyenLogger->addAdyenResult(
                        'Error when trying to create a new quote, ' .
                        'the previous quote has been restored instead: ' . $e->getMessage()
                    );
                }
            }

            // Always cancel the order if the payment has failed
            if (!$order->canCancel()) {
                $order->setState(\Magento\Sales\Model\Order::STATE_NEW);
            }

            $order->cancel()->save();

            $this->adyenLogger->error(
                sprintf("Payment details call failed for action or 3ds2 payment method, resultcode is %s Raw API responds: %s",
                    $result['resultCode'],
                    print_r($result, true)
                ));

            throw new \Magento\Framework\Exception\LocalizedException(__('The payment is REFUSED.'));
        }

        $response['result'] = $result['resultCode'];
        return json_encode($response);
    }

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     * @deprecated Will be removed in 7.0.0
     */
    protected function getOrder()
    {
        $incrementId = $this->checkoutSession->getLastRealOrderId();
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);

        return $order;
    }
}

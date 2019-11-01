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

use \Adyen\Payment\Api\AdyenThreeDS2ProcessInterface;

class AdyenThreeDS2Process implements AdyenThreeDS2ProcessInterface
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
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var
     */
    private $order;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * AdyenThreeDS2Process constructor.
     *
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->orderFactory = $orderFactory;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @api
     * @param string $payload
     * @return string
     */
    public function initiate($payload)
    {
        // Decode payload from frontend
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Magento\Framework\Exception\LocalizedException(__('3D secure 2.0 failed because the request was not a valid JSON'));
        }

        // Get payment and cart information from session
        $order = $this->getOrder();
        $payment = $order->getPayment();

        // Init payments/details request
        $result = [];

        if ($paymentData = $payment->getAdditionalInformation("threeDS2PaymentData")) {
            // Add payment data into the request object
            $request = [
                "paymentData" => $payment->getAdditionalInformation("threeDS2PaymentData")
            ];

            // unset payment data from additional information
            $payment->unsAdditionalInformation("threeDS2PaymentData");
        } else {
            $this->adyenLogger->error("3D secure 2.0 failed, payment data not found");
            throw new \Magento\Framework\Exception\LocalizedException(__('3D secure 2.0 failed, payment data not found'));
        }

        // Depends on the component's response we send a fingerprint or the challenge result
        if (!empty($payload['details']['threeds2.fingerprint'])) {
            $request['details']['threeds2.fingerprint'] = $payload['details']['threeds2.fingerprint'];
        } elseif (!empty($payload['details']['threeds2.challengeResult'])) {
            $request['details']['threeds2.challengeResult'] = $payload['details']['threeds2.challengeResult'];
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
            return $this->adyenHelper->buildThreeDS2ProcessResponseJson($result['resultCode'], $result['authentication']['threeds2.challengeToken']);
        }

        // Save the payments response because we are going to need it during the place order flow
        $payment->setAdditionalInformation("paymentsResponse", $result);

        // To actually save the additional info changes into the quote
        $order->save();


        $response = [];

        if($result['resultCode'] != 'Authorised') {
            $this->checkoutSession->restoreQuote();

            // Always cancel the order if the paymenth has failed
            if (!$order->canCancel()) {
                $order->setState(\Magento\Sales\Model\Order::STATE_NEW);
            }

            $order->cancel()->save();

            throw new \Magento\Framework\Exception\LocalizedException(__('The payment is REFUSED.'));
        }

        $response['result'] = $result['resultCode'];
        return json_encode($response);
    }

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        if (!$this->order) {
            $incrementId = $this->checkoutSession->getLastRealOrderId();
            $this->order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->order;
    }
}

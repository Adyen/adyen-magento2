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
     * AdyenThreeDS2Process constructor.
     *
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @api
     * @param mixed $payload
     * @return mixed
     */
    public function initiate($payload)
    {
        // Decode payload from frontend
        $payload = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Magento\Framework\Exception\LocalizedException(__('3D secure 2.0 failed because the request was not a valid JSON'));
        }

        // Get payment and cart information from session
        $quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();

        // Init payments/details request
        $request = [
            "paymentData" => $payment->getAdditionalInformation("threeDS2PaymentData")
        ];

        // unset payment data from additional information
        $payment->unsAdditionalInformation("threeDS2PaymentData");

        // Depends on the component's response we send a fingerprint or the challenge result
        if (!empty($payload['details']['threeds2.fingerprint'])) {
            $request['details']['threeds2.fingerprint'] = $payload['details']['threeds2.fingerprint'];
        } elseif (!empty($payload['details']['threeds2.challengeResult'])) {
            $request['details']['threeds2.challengeResult'] = $payload['details']['threeds2.challengeResult'];
        }

        // Send the request
        try {
            $client = $this->adyenHelper->initializeAdyenClient($quote->getStoreId());
            $service = $this->adyenHelper->createAdyenCheckoutService($client);
            $result = $service->paymentsDetails($request);
        } catch (\Adyen\AdyenException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('3D secure 2.0 failed'));
        }

        // Check if result is challenge shopper, if yes return the token
        if (!empty($result['resultCode']) &&
            $result['resultCode'] === 'ChallengeShopper' &&
            !empty($result['authentication']['threeds2.challengeToken'])
        ) {
            return json_encode(
                array(
                    'threeDS2' => true,
                    'type' => $result['resultCode'],
                    'token' => $result['authentication']['threeds2.challengeToken']
                )
            );
        }

        // Payment can get back to the original flow
        $payment->setAdditionalInformation("paymentsResponse", $result);
        $payment->setAdditionalInformation('placeOrder', true);
        $quote->save();

        // 3DS2 flow is done, original place order flow can continue from frontend
        return json_encode(
            array(
                'threeDS2' => false
            )
        );
    }
}

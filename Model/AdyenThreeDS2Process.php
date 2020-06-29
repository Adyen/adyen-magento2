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
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * AdyenThreeDS2Process constructor.
     *
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->orderFactory = $orderFactory;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param string $payload
     * @return string
     * @api
     */
    public function initiate($payload)
    {
        // Decode payload from frontend
        // TODO implement interface to handle the request the correct way
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('3D secure 2.0 failed because the request was not a valid JSON')
            );
        }

        // Validate if order id is present
        if (empty($payload['orderId'])) {
            $order = $this->getOrder();
            // In the next major release remove support for retrieving order from session and throw exception instead
            //throw new \Magento\Framework\Exception\LocalizedException
            //(__('3D secure 2.0 failed because of a missing order id'));
        } else {
            // Create order by order id
            $order = $this->orderFactory->create()->load($payload['orderId']);
        }

        $payment = $order->getPayment();



        // Unset action from additional info since it is not needed anymore
        $payment->unsAdditionalInformation("action");

        // TODO validate and format the request root level keys
        $request = $payload;

        // Init payments/details request
        $result = [];

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
            !empty($result['action'])
        ) {
            return json_encode($result['action']);
        }

        // Save the payments response because we are going to need it during the place order flow
        $payment->setAdditionalInformation("paymentsResponse", $result);

        // To actually save the additional info changes into the quote
        $order->save();

        $response = [];

        if ($result['resultCode'] != 'Authorised') {
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
     * @deprecated Will be removed in 7.0.0
     */
    protected function getOrder()
    {
        $incrementId = $this->checkoutSession->getLastRealOrderId();
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);

        return $order;
    }
}

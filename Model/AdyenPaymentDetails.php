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

use Adyen\AdyenException;
use Adyen\Payment\Api\AdyenPaymentDetailsInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;

class AdyenPaymentDetails implements AdyenPaymentDetailsInterface
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
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var PaymentResponseHandler
     */
    private $paymentResponseHandler;

    /**
     * AdyenPaymentDetails constructor.
     *
     * @param Session $checkoutSession
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentResponseHandler $paymentResponseHandler
     */
    public function __construct(
        Session $checkoutSession,
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        OrderRepositoryInterface $orderRepository,
        PaymentResponseHandler $paymentResponseHandler
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->orderRepository = $orderRepository;
        $this->paymentResponseHandler = $paymentResponseHandler;
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
            throw new LocalizedException(__('Payment details call failed because the request was not a valid JSON'));
        }

        //Get order from payload and remove orderId from the array
        if (empty($payload['orderId'])) {
            throw new LocalizedException
            (__('Payment details call failed because of a missing order ID'));
        } else {
            $order = $this->orderRepository->get($payload['orderId']);
            //TODO send state.data from frontend so no unsetting is necessary
            unset($payload['orderId']);
        }

        $payment = $order->getPayment();

        // Send the request
        try {
            $client = $this->adyenHelper->initializeAdyenClient($order->getStoreId());
            $service = $this->adyenHelper->createAdyenCheckoutService($client);
            $paymentDetails = $service->paymentsDetails($payload);
        } catch (AdyenException $e) {
            $this->adyenLogger->error("Payment details call failed: " . $e->getMessage());
            $this->checkoutSession->restoreQuote();

            // accept cancellation request, restore quote
            if (!empty($payload['cancelled'])) {
                throw new LocalizedException(__('Payment has been cancelled'));
            } else {
                throw new LocalizedException(__('Payment details call failed'));
            }
        }

        // Handle response
        if (!$this->paymentResponseHandler->handlePaymentResponse($paymentDetails, $payment, $order)) {
            $this->checkoutSession->restoreQuote();
            throw new LocalizedException(__('The payment is REFUSED.'));
        }

        $action = null;
        if (!empty($paymentDetails['action'])) {
            $action = $paymentDetails['action'];
        }

        $additionalData = null;
        if (!empty($paymentDetails['additionalData'])) {
            $additionalData = $paymentDetails['additionalData'];
        }

        return json_encode($this->paymentResponseHandler->formatPaymentResponse($paymentDetails['resultCode'], $action, $additionalData));
    }
}

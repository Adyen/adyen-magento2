<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Service\Validator\DataArrayValidator;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\ValidatorException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;

class PaymentsDetails
{
    const PAYMENTS_DETAILS_KEYS = [
        'details',
        'paymentData',
        'threeDSAuthenticationOnly'
    ];

    private Session $checkoutSession;
    private Data $adyenHelper;
    private AdyenLogger $adyenLogger;
    private PaymentResponseHandler $paymentResponseHandler;
    private Idempotency $idempotencyHelper;
    private Order $orderHelper;
    private OrderRepository $orderRepository;

    public function __construct(
        Session $checkoutSession,
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        PaymentResponseHandler $paymentResponseHandler,
        Idempotency $idempotencyHelper,
        Order $orderHelper,
        OrderRepository $orderRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->paymentResponseHandler = $paymentResponseHandler;
        $this->idempotencyHelper = $idempotencyHelper;
        $this->orderHelper = $orderHelper;
        $this->orderRepository = $orderRepository;
    }

    public function initiatePaymentDetails(OrderInterface $order, string $payload): string
    {
        // Decode payload from frontend
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidatorException(__('Payment details call failed because the request was not a valid JSON'));
        }

        $payment = $order->getPayment();
        $apiPayload = DataArrayValidator::getArrayOnlyWithApprovedKeys($payload, self::PAYMENTS_DETAILS_KEYS);

        // Send the request
        try {
            $client = $this->adyenHelper->initializeAdyenClient($order->getStoreId());
            $service = $this->adyenHelper->createAdyenCheckoutService($client);

            $requestOptions['idempotencyKey'] = $this->idempotencyHelper->generateIdempotencyKey($apiPayload);

            $paymentDetails = $service->paymentsDetails($apiPayload, $requestOptions);
        } catch (AdyenException $e) {
            $this->adyenLogger->error("Payment details call failed: " . $e->getMessage());
            $this->checkoutSession->restoreQuote();

            // accept cancellation request, restore quote
            if (!empty($payload['cancelled'])) {
                throw $this->createCancelledException();
            } else {
                throw new ValidatorException(__('Payment details call failed'));
            }
        }

        // Handle response
        if (!$this->paymentResponseHandler->handlePaymentResponse($paymentDetails, $payment, $order)) {
            $this->checkoutSession->restoreQuote();
            throw new ValidatorException(__('The payment is REFUSED.'));
        }

        $action = null;
        if (!empty($paymentDetails['action'])) {
            $action = $paymentDetails['action'];
        }

        $additionalData = null;
        if (!empty($paymentDetails['additionalData'])) {
            $additionalData = $paymentDetails['additionalData'];
        }

        if (!in_array($paymentDetails['resultCode'], PaymentResponseHandler::ACTION_REQUIRED_STATUSES)) {
            /* Change order state from pending_payment to new and expect authorisation webhook
             * if no additional action is required according to /paymentDetails response. */
            $order = $this->orderHelper->setStatusOrderCreation($order);
            $this->orderRepository->save($order);
        }

        return json_encode(
            $this->paymentResponseHandler->formatPaymentResponse(
                $paymentDetails['resultCode'],
                $action,
                $additionalData
            )
        );
    }

    private function createCancelledException(): ValidatorException
    {
        return new ValidatorException(__('Payment has been cancelled'));
    }
}

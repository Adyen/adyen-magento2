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

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenPaymentsDetailsInterface;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\PaymentsDetails;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Sales\Api\OrderRepositoryInterface;

class AdyenPaymentsDetails implements AdyenPaymentsDetailsInterface
{
    private OrderRepositoryInterface $orderRepository;
    private PaymentsDetails $paymentsDetails;
    private PaymentResponseHandler $paymentResponseHandler;
    private Session $checkoutSession;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        PaymentsDetails $paymentsDetails,
        PaymentResponseHandler $paymentResponseHandler,
        Session $checkoutSession
    ) {
        $this->orderRepository = $orderRepository;
        $this->paymentsDetails = $paymentsDetails;
        $this->paymentResponseHandler = $paymentResponseHandler;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param string $payload
     * @param string $orderId
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @api
     */
    public function initiate(string $payload, string $orderId): string
    {
        $order = $this->orderRepository->get(intval($orderId));

        // Decode payload from frontend
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidatorException(
                __('Payment details call failed because the request was not a valid JSON')
            );
        }

        $response = $this->paymentsDetails->initiatePaymentDetails($order, $payload);

        // Handle response
        if (!$this->paymentResponseHandler->handlePaymentsDetailsResponse($response, $order)) {
            $this->checkoutSession->restoreQuote();
            throw new ValidatorException(__('The payment is REFUSED.'));
        }

        return json_encode(
            $this->paymentResponseHandler->formatPaymentResponse(
                $response['resultCode'],
                $response['action'] ?? null,
                !empty($response['donationToken'])
            )
        );
    }
}

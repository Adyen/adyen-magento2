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

use Adyen\Payment\Api\GuestAdyenPaymentsDetailsInterface;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\PaymentsDetails;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class GuestAdyenPaymentsDetails implements GuestAdyenPaymentsDetailsInterface
{
    private OrderRepositoryInterface $orderRepository;
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private PaymentsDetails $paymentsDetails;
    private PaymentResponseHandler $paymentResponseHandler;
    private Session $checkoutSession;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        PaymentsDetails $paymentsDetails,
        PaymentResponseHandler $paymentResponseHandler,
        Session $checkoutSession
    ) {
        $this->orderRepository = $orderRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->paymentsDetails = $paymentsDetails;
        $this->paymentResponseHandler = $paymentResponseHandler;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param string $payload
     * @param string $orderId
     * @param string $cartId
     * @return string
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @api
     */
    public function initiate(string $payload, string $orderId, string $cartId): string
    {
        $order = $this->orderRepository->get(intval($orderId));

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        if ($order->getQuoteId() != $quoteId) {
            throw new NotFoundException(
                __("The entity that was requested doesn't exist. Verify the entity and try again.")
            );
        }

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
                $response['additionalData'] ?? null
            )
        );
    }
}

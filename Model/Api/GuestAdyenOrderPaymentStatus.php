<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\GuestAdyenOrderPaymentStatusInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class GuestAdyenOrderPaymentStatus implements GuestAdyenOrderPaymentStatusInterface
{
    protected OrderRepositoryInterface $orderRepository;
    protected AdyenLogger $adyenLogger;
    protected Data $adyenHelper;
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private PaymentResponseHandler $paymentResponseHandler;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        AdyenLogger $adyenLogger,
        Data $adyenHelper,
        PaymentResponseHandler $paymentResponseHandler,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        $this->paymentResponseHandler = $paymentResponseHandler;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    public function getOrderPaymentStatus(string $orderId, string $cartId): string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        $order = $this->orderRepository->get($orderId);

        if ($order->getQuoteId() !== $quoteId) {
            $errorMessage = sprintf("Order for ID %s not found!", $orderId);
            $this->adyenLogger->error($errorMessage);

            throw new NotFoundException(
                __("The entity that was requested doesn't exist. Verify the entity and try again.")
            );
        }

        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();

        if (empty($additionalInformation['resultCode'])) {
            $this->adyenLogger->addAdyenInfoLog('resultCode is empty in the payment\'s additional information');
            return json_encode(
                $this->paymentResponseHandler->formatPaymentResponse(PaymentResponseHandler::ERROR)
            );
        }

        return json_encode($this->paymentResponseHandler->formatPaymentResponse(
            $additionalInformation['resultCode'],
            !empty($additionalInformation['action']) ? $additionalInformation['action'] : null,
            !empty($additionalInformation['additionalData']) ? $additionalInformation['additionalData'] : null
        ));
    }
}

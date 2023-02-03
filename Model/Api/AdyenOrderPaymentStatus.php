<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenOrderPaymentStatusInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Sale\Collection;
use Magento\Framework\Exception\NoSuchEntityException;

class AdyenOrderPaymentStatus implements AdyenOrderPaymentStatusInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var Data
     */
    protected $adyenHelper;

    /**
     * @var PaymentResponseHandler
     */
    private $paymentResponseHandler;

    /**
     * @var MaskedQuoteIdToQuoteId
     */
    private $maskedQuoteIdToQuoteId;


    /**
     * @var Collection
     */
    private $salesOrderCollection;
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;


    /**
     * AdyenOrderPaymentStatus constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param QuoteRepository $quoteRepository
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     * @param Data $adyenHelper
     * @param PaymentResponseHandler $paymentResponseHandler
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        QuoteRepository          $quoteRepository,
        Collection               $salesOrderCollection,
        MaskedQuoteIdToQuoteId   $maskedQuoteIdToQuoteId,
        AdyenLogger              $adyenLogger,
        Data                     $adyenHelper,
        PaymentResponseHandler   $paymentResponseHandler
    )
    {
        $this->orderRepository = $orderRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteRepository = $quoteRepository;
        $this->salesOrderCollection = $salesOrderCollection;
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        $this->paymentResponseHandler = $paymentResponseHandler;
    }

    /**
     * @param string $cartId
     * @return bool|string
     */


    public function getguestOrderPaymentStatus($cartId)
    {
        $unmaskedCartId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        $loadOrder = $this->salesOrderCollection->addFieldToFilter('quote_id', $unmaskedCartId);
        $orderArray = $loadOrder->getData();
        $orderId = $orderArray[0]['entity_id'];

        if (isset($orderId)) {
            try {

//            $quote = $this->quoteRepository->get($unmask);

                $order = $this->orderRepository->get($orderId);


            } catch (NoSuchEntityException $exception) {
                $this->adyenLogger->error('Order not found.');
                return json_encode(
                    $this->paymentResponseHandler->formatPaymentResponse(PaymentResponseHandler::ERROR)
                );
            }

            $payment = $order->getPayment();
            $additionalInformation = $payment->getAdditionalInformation();

            if (empty($additionalInformation['resultCode'])) {
                $this->adyenLogger->info('resultCode is empty in the payment\'s additional information');
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

    public function getOrderPaymentStatus($orderId)
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $exception) {
            $this->adyenLogger->error('Order not found.');
            return json_encode(
                $this->paymentResponseHandler->formatPaymentResponse(PaymentResponseHandler::ERROR)
            );
        }

        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();

        if (empty($additionalInformation['resultCode'])) {
            $this->adyenLogger->info('resultCode is empty in the payment\'s additional information');
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

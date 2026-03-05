<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AuthorizationHandler;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection as AdyenPaymentResponseCollection;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

readonly class AuthorizeAfterOrderPlacement implements ObserverInterface
{
    /**
     * @param AuthorizationHandler $authorizationHandler
     * @param AdyenPaymentResponseCollection $adyenPaymentResponseCollection
     * @param PaymentMethods $paymentMethods
     * @param AdyenLogger $adyenLogger
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        private AuthorizationHandler $authorizationHandler,
        private AdyenPaymentResponseCollection $adyenPaymentResponseCollection,
        private PaymentMethods $paymentMethods,
        private AdyenLogger $adyenLogger,
        private OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getData('order');

        if (!$this->paymentMethods->isAdyenPayment($order->getPayment()->getMethod())) {
            return;
        }

        try {
            $paymentResponses = $this->adyenPaymentResponseCollection
                ->getPaymentResponsesWithMerchantReferences([$order->getIncrementId()]);

            foreach ($paymentResponses as $paymentResponse) {
                if ($paymentResponse[PaymentResponseInterface::RESULT_CODE] !== PaymentResponseHandler::AUTHORISED) {
                    continue;
                }

                $response = json_decode($paymentResponse['response'], true);

                $order = $this->authorizationHandler->execute(
                    $order,
                    $response['paymentMethod']['brand'],
                    $response['pspReference'],
                    $response['amount']['value'],
                    $response['amount']['currency'],
                    $response['additionalData']
                );

                $this->orderRepository->save($order);
            }
        } catch (Exception $e) {
            $this->adyenLogger->error(
                sprintf(
                    'Failed to process authorization after order placement for order #%s: %s',
                    $order->getIncrementId(),
                    $e->getMessage()
                )
            );
        }
    }
}

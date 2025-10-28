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
use Adyen\Model\Checkout\CancelOrderRequest;
use Adyen\Model\Checkout\CreateOrderRequest;
use Adyen\Client;
use Adyen\Model\Checkout\CreateOrderResponse;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Service\Checkout\OrdersApi as CheckoutOrdersApi;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Throwable;

class OrdersApi
{
    const DATA_KEY_CHECKOUT_API_ORDER = 'checkoutApiOrder';

    /**
     * Temporary storage for Orders API response for partial payments
     *
     * Holds `orderData` and `pspReference` values.
     *
     * @var array|null
     */
    private ?array $checkoutApiOrder = null;

    /**
     * @param Config $configHelper
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly Config $configHelper,
        private readonly Data $adyenHelper,
        private readonly AdyenLogger $adyenLogger
    ) {}

    /**
     * @param int $amount
     * @param string $currency
     * @param string $storeId
     * @param string $merchantReference
     * @return array
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function createOrder(string $merchantReference, int $amount, string $currency, string $storeId): array
    {
        $request = $this->buildOrdersRequest($amount, $currency, $merchantReference, $storeId);

        $client = $this->adyenHelper->initializeAdyenClient($storeId);
        $checkoutService = new CheckoutOrdersApi($client);

        try {
            $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/orders');
            $responseObj = $checkoutService->orders(new CreateOrderRequest($request));
            $this->setCheckoutApiOrder($responseObj->getPspReference(), $responseObj->getOrderData());
            $response = $responseObj->toArray();
        } catch (AdyenException $e) {
            $this->adyenLogger->error(
                "Connection to the endpoint failed. Check the Adyen Live endpoint prefix configuration."
            );

            throw $e;
        }
        $this->adyenHelper->logResponse($response);

        return $response;
    }

    /**
     * @param int $amount
     * @param string $currency
     * @param string $merchantReference
     * @param string $storeId
     * @return array
     */
    private function buildOrdersRequest(
        int $amount,
        string $currency,
        string $merchantReference,
        string $storeId
    ): array {
        $merchantAccount = $this->configHelper->getMerchantAccount($storeId);

        return [
            'reference' => $merchantReference,
            'amount' => [
                'value' => $amount,
                'currency' => $currency
            ],
            'merchantAccount' => $merchantAccount
        ];
    }

    /**
     * @param OrderInterface $order
     * @param string $pspReference
     * @param string $orderData
     * @return void
     */
    public function cancelOrder(OrderInterface $order, string $pspReference, string $orderData): void
    {
        try {
            $storeId = $order->getStoreId();
            $client = $this->adyenHelper->initializeAdyenClient($storeId);
            $service = $this->adyenHelper->initializeOrdersApi($client);

            $request = [
                'order' => [
                    'pspReference' => $pspReference,
                    'orderData' => $orderData
                ],
                'merchantAccount' => $this->configHelper->getMerchantAccount($storeId),
            ];

            $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/orders/cancel');
            $response = $service->cancelOrder(new CancelOrderRequest($request));
            $this->adyenHelper->logResponse($response->toArray());
        } catch (Throwable $e) {
            $this->adyenLogger->error(__('Error while trying to cancel the order: %1', $e->getMessage()), [
                'pspReference' => $pspReference
            ]);
        }
    }

    /**
     * Sets the pspReference and orderData of the checkoutOrderApi object in the temporary storage
     *
     * @param string $pspReference
     * @param string $orderData
     * @return void
     */
    public function setCheckoutApiOrder(string $pspReference, string $orderData): void
    {
        $this->checkoutApiOrder = [
            'pspReference' => $pspReference,
            'orderData' => $orderData
        ];
    }

    /**
     * Returns the value of the Create Order API call from the temporary storage
     *
     * @return array|null
     */
    public function getCheckoutApiOrder(): ?array
    {
        return $this->checkoutApiOrder;
    }
}

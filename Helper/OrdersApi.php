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
use Adyen\Client;
use Adyen\ConnectionException;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;

class OrdersApi
{
    private Config $configHelper;
    private Data $adyenHelper;
    private AdyenLogger $adyenLogger;

    public function __construct(
        Config $configHelper,
        Data $adyenHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->configHelper = $configHelper;
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param int $amount
     * @param string $currency
     * @param string $storeId
     * @param string $merchantReference
     * @return array
     * @throws AdyenException
     * @throws ConnectionException
     * @throws NoSuchEntityException
     */
    public function createOrder(string $merchantReference, int $amount, string $currency, string $storeId): array
    {
        $request = $this->buildOrdersRequest($amount, $currency, $merchantReference, $storeId);

        $client = $this->adyenHelper->initializeAdyenClient($storeId);
        $checkoutService = $this->adyenHelper->createAdyenCheckoutService($client);

        try {
            $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/orders');
            $response = $checkoutService->orders($request);
        } catch (ConnectionException $e) {
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
}

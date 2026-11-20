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
use Adyen\Model\Checkout\BalanceCheckRequest;
use Adyen\Model\Checkout\BalanceCheckResponse;
use Adyen\Model\Checkout\CreateOrderRequest;
use Adyen\Client;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Service\Checkout\OrdersApi as CheckoutOrdersApi;
use Magento\Framework\Exception\NoSuchEntityException;

class OrdersApi
{
    /**
     * @var Config
     */
    private Config $configHelper;

    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * @var AdyenLogger
     */
    private AdyenLogger $adyenLogger;

    /**
     * @param Config $configHelper
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     */
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
     * @param array $request
     * @param int $storeId
     * @return BalanceCheckResponse
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function getGiftcardBalance(array $request, int $storeId): BalanceCheckResponse
    {
        $request['merchantAccount'] = $this->configHelper->getMerchantAccount($storeId);

        try {
            $client = $this->adyenHelper->initializeAdyenClient($storeId);
            $service = $this->adyenHelper->initializeOrdersApi($client);
            $response = $service->getBalanceOfGiftCard(new BalanceCheckRequest($request));

            if ($response->getResultCode() === BalanceCheckResponse::RESULT_CODE_FAILED) {
                // Balance endpoint doesn't send HTTP status 422 for invalid PIN, manual handling required.
                $errorMessage = $response['additionalData']['acquirerResponseCode'] ?? 'Unknown error!';
                throw new AdyenException($errorMessage);
            }

            return $response;
        } catch (AdyenException $e) {
            $this->adyenLogger->error(
                sprintf("An error occurred during balance check! %s", $e->getMessage())
            );

            throw $e;
        }
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

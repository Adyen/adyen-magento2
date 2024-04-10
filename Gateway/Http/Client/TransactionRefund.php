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

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Class TransactionSale
 */
class TransactionRefund implements TransactionRefundInterface
{
    private Data $adyenHelper;
    private Idempotency $idempotencyHelper;

    public function __construct(
        Data $adyenHelper,
        Idempotency $idempotencyHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->idempotencyHelper = $idempotencyHelper;
    }

    public function placeRequest(TransferInterface $transferObject): array
    {
        $requests = $transferObject->getBody();
        $headers = $transferObject->getHeaders();
        $clientConfig = $transferObject->getClientConfig();
        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = $this->adyenHelper->createAdyenCheckoutService($client);

        foreach ($requests as $request) {
            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $headers['idempotencyExtraData'] ?? null
            );
            $requestOptions['idempotencyKey'] = $idempotencyKey;
            $requestOptions['headers'] = $this->adyenHelper->buildRequestHeaders();

            $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/refunds');
            try {
                $response = $service->refunds($request, $requestOptions);
                // Add amount original reference and amount information to response
                $response[self::REFUND_AMOUNT] = $request['amount']['value'];
                $response[self::REFUND_CURRENCY] = $request['amount']['currency'];
                $response[self::ORIGINAL_REFERENCE] = $request['paymentPspReference'];
            } catch (AdyenException $e) {
                $response = ['error' => $e->getMessage()];
            }
        }

        $this->adyenHelper->logResponse($response);
        $responses[] = $response;

        return $responses;
    }
}

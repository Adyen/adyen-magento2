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
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionCancel implements ClientInterface
{
    private Data $adyenHelper;
    private Idempotency $idempotencyHelper;

    public function __construct(
        Data        $adyenHelper,
        Idempotency $idempotencyHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->idempotencyHelper = $idempotencyHelper;
    }

    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();
        $headers = $transferObject->getHeaders();
        $clientConfig = $transferObject->getClientConfig();
        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = $this->adyenHelper->createAdyenCheckoutService($client);
        $response = [];

        foreach ($request as $requests) {
            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $requests,
                $headers['idempotencyExtraData'] ?? null
            );
            $requestOptions['idempotencyKey'] = $idempotencyKey;
            $requestOptions['headers'] = $this->adyenHelper->buildRequestHeaders();
            $this->adyenHelper->logRequest($requests, Client::API_CHECKOUT_VERSION, '/cancels');
            try {
                $responses = $service->cancels($requests, $requestOptions);
            } catch (AdyenException $e) {
                $response['error'] = $e->getMessage();
            }
            $this->adyenHelper->logResponse($responses);

            $response = $responses;
        }

        return $response;
    }

}

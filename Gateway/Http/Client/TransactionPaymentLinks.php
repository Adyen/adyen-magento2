<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Model\Checkout\PaymentLinkRequest;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Service\Checkout\PaymentLinksApi;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionPaymentLinks implements ClientInterface
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
        $request = $transferObject->getBody();
        $headers = $transferObject->getHeaders();
        $clientConfig = $transferObject->getClientConfig();

        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = new PaymentLinksApi($client);

        // If the payment links call is already done return the request
        if (!empty($request['resultCode'])) {
            //Initiate has already a response
            return $request;
        }

        $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
            $request,
            $headers['idempotencyExtraData'] ?? null
        );

        $requestOptions['idempotencyKey'] = $idempotencyKey;
        $requestOptions['headers'] = $this->adyenHelper->buildRequestHeaders();

        $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/paymentLinks');
        try {
            $responseObj = $service->paymentLinks(new PaymentLinkRequest($request), $requestOptions);
            //@todo when supported, use $responseObj->toArray()
            $response = json_decode(json_encode($responseObj->jsonSerialize()), true);
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
        }
        $this->adyenHelper->logResponse($response);

        return $response;
    }
}

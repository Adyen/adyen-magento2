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
use Adyen\Model\Checkout\PaymentCancelRequest;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Service\Checkout\ModificationsApi;
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
        $requests = $transferObject->getBody();
        $headers = $transferObject->getHeaders();
        $clientConfig = $transferObject->getClientConfig();
        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = new ModificationsApi($client);
        $responseData = [];

        foreach ($requests as $request) {
            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $headers['idempotencyExtraData'] ?? null
            );
            $requestOptions['idempotencyKey'] = $idempotencyKey;
            $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/cancels');
            $paymentCancelRequest = new PaymentCancelRequest($request);

            try {
                $response = $service->cancelAuthorisedPaymentByPspReference(
                    $request['paymentPspReference'],
                    $paymentCancelRequest,
                    $requestOptions
                );
                $responseData = (array) $response->jsonSerialize();
                $this->adyenHelper->logResponse($responseData);
            } catch (AdyenException $e) {
                $this->adyenHelper->logAdyenException($e);
            }
        }

        return $responseData;
    }
}

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
use Adyen\Model\Checkout\PaymentRefundRequest;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Service\Checkout\ModificationsApi;
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
        $service = $this->adyenHelper->initializeModificationsApi($client);
        $responses = [];

        foreach ($requests as $request) {
            $responseData = [];
            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $headers['idempotencyExtraData'] ?? null
            );
            $requestOptions['idempotencyKey'] = $idempotencyKey;
            $requestOptions['headers'] = $this->adyenHelper->buildRequestHeaders();
            $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/refunds');
            $paymentRefundRequest = new PaymentRefundRequest($request);

            try {
                $response = $service->refundCapturedPayment(
                    $request['paymentPspReference'],
                    $paymentRefundRequest,
                    $requestOptions
                );
                //@todo when supported, use $response->toArray()
                $responseData = json_decode(json_encode($response->jsonSerialize()), true);
                // Add amount original reference and amount information to response
                $responseData[self::REFUND_AMOUNT] = $request['amount']['value'];
                $responseData[self::REFUND_CURRENCY] = $request['amount']['currency'];
                $responseData[self::ORIGINAL_REFERENCE] = $request['paymentPspReference'];
                $this->adyenHelper->logResponse($responseData);
            } catch (AdyenException $e) {
                $this->adyenHelper->logAdyenException($e);
            }
            $responses[] = $responseData;
        }

        return $responses;
    }
}

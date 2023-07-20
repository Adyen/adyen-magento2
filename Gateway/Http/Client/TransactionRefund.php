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
use Adyen\Service\Modification;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionRefund implements ClientInterface
{
    const REFUND_AMOUNT = 'refund_amount';
    const REFUND_CURRENCY = 'refund_currency';
    const ORIGINAL_REFERENCE = 'original_reference';

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

        foreach ($requests as $request) {
            //Check if it is a MOTO Transaction
            if(isset($clientConfig['isMotoTransaction']) && $clientConfig['isMotoTransaction'] === true) {
                $client = $this->adyenHelper->initializeAdyenClient(
                    $clientConfig['storeId'],
                    null,
                    $request['merchantAccount']
                );
            } else {
                $client = $this->adyenHelper->initializeAdyenClient($clientConfig['storeId']);
            }

            $service = new Modification($client);

            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $headers['idempotencyExtraData'] ?? null
            );

            $requestOptions['idempotencyKey'] = $idempotencyKey;

            $this->adyenHelper
                ->logRequest($request, Client::API_PAYMENT_VERSION, '/pal/servlet/Payment/{version}/refund');
            if(isset($clientConfig['isMotoTransaction']) && $clientConfig['isMotoTransaction'] === true) {
                try {
                    $response = $service->refund($request);
                } catch (\Adyen\AdyenException $e) {
                    $response = ['error' => $e->getMessage()];
                }
            } else {
                try {
                    $response = $service->refund($request, $requestOptions);

                    // Add amount original reference and amount information to response
                    $response[self::REFUND_AMOUNT] = $request['modificationAmount']['value'];
                    $response[self::REFUND_CURRENCY] = $request['modificationAmount']['currency'];
                    $response[self::ORIGINAL_REFERENCE] = $request['originalReference'];
                } catch (AdyenException $e) {
                    $response = ['error' => $e->getMessage()];
                }
            }
            $this->adyenHelper->logResponse($response);

            $responses[] = $response;
        }
        return $responses;
    }
}

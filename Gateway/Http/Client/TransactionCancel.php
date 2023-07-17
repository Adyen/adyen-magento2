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
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionCancel implements ClientInterface
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

        //Check if it is a MOTO Transaction
        if(isset($clientConfig['isMotoTransaction']) && $clientConfig['isMotoTransaction'] === true) {
            $client = $this->adyenHelper->initializeAdyenClient(
                $clientConfig['storeId'],
                null,
                $request['merchantAccount']
            );
        } else {
            $client = $this->adyenHelper->initializeAdyenClient($transferObject->getClientConfig()['storeId']);
        }

        $service = new Modification($client);
        $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
            $request,
            $headers['idempotencyExtraData'] ?? null
        );

        $requestOptions['idempotencyKey'] = $idempotencyKey;

        $this->adyenHelper
            ->logRequest($request, Client::API_PAYMENT_VERSION, '/pal/servlet/Payment/{version}/cancel');
        try {
            $response = $service->cancel($request, $requestOptions);
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
        }
        $this->adyenHelper->logResponse($response);

        return $response;
    }
}

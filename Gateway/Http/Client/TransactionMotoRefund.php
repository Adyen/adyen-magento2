<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\Client;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Http\ClientInterface;

/**
 * Class TransactionSale
 */
class TransactionMotoRefund implements TransactionRefundInterface
{
    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var Idempotency
     */
    private $idempotencyHelper;

    /**
     * PaymentRequest constructor.
     * @param Data $adyenHelper
     * @param Idempotency $idempotencyHelper
     */
    public function __construct(
        Data $adyenHelper,
        Idempotency $idempotencyHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->idempotencyHelper = $idempotencyHelper;
    }

    /**
     * @param TransferInterface $transferObject
     * @return null
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $requests = $transferObject->getBody();
        $headers = $transferObject->getHeaders();

        $clientConfig = $transferObject->getClientConfig();

        $responses = [];

        foreach ($requests as $request) {
            // call lib

            $client = $this->adyenHelper->initializeAdyenClient(
                $clientConfig['storeId'],
                null,
                $request['merchantAccount']
            );

            $service = $this->adyenHelper->createAdyenCheckoutService($client);

            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $headers['idempotencyExtraData'] ?? null
            );

            $requestOptions['idempotencyKey'] = $idempotencyKey;

            $this->adyenHelper
                ->logRequest($request, Client::API_CHECKOUT_VERSION, '/refunds');
            try {
                $response = $service->refunds($request, $requestOptions);

                // Add amount original reference and amount information to response
                $response[self::REFUND_AMOUNT] = $request['amount']['value'];
                $response[self::REFUND_CURRENCY] = $request['amount']['currency'];
                $response[self::ORIGINAL_REFERENCE] = $request['paymentPspReference'];
            } catch (\Adyen\AdyenException $e) {
                $response = ['error' => $e->getMessage()];
            }
            $this->adyenHelper->logResponse($response);

            $responses[] = $response;
        }

        return $responses;
    }
}

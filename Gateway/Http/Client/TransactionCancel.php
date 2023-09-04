<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Service\Checkout\ModificationsApi;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Class TransactionSale
 */
class TransactionCancel implements ClientInterface
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
        Data        $adyenHelper,
        Idempotency $idempotencyHelper
    )
    {
        $this->adyenHelper = $adyenHelper;
        $this->idempotencyHelper = $idempotencyHelper;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();
        $headers = $transferObject->getHeaders();

        $client = $this->adyenHelper->initializeAdyenClient();
        $service = $this->adyenHelper->createAdyenCheckoutService($client);


        $response = [];


        foreach ($request as $requests) {

            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $requests,
                $headers['idempotencyExtraData'] ?? null
            );

            $requestOptions['idempotencyKey'] = $idempotencyKey;

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
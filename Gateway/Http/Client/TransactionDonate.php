<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Model\Checkout\DonationPaymentRequest;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Service\Checkout\DonationsApi;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionDonate extends BaseTransaction
{
    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var Data
     */
    protected Data $adyenHelper;

    /**
     * @var Idempotency
     */
    private Idempotency $idempotencyHelper;

    /**
     * @param Data $adyenHelper
     * @param Idempotency $idempotencyHelper
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function __construct(
        Data $adyenHelper,
        Idempotency $idempotencyHelper
    ) {
        parent::__construct($adyenHelper);
        $this->idempotencyHelper = $idempotencyHelper;
        $this->client = $this->adyenHelper->initializeAdyenClient();
    }

    /**
     * @param TransferInterface $transferObject
     * @return array
     * @throws AdyenException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();
        $requestOptions['headers'] = $this->requestHeaders($transferObject);
        $service = new DonationsApi($this->client);

        $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
            $request,
            $requestOptions['headers']['idempotencyExtraData'] ?? null
        );

        $requestOptions['idempotencyKey'] = $idempotencyKey;
        $request['applicationInfo'] = $this->adyenHelper->buildApplicationInfo($this->client);

        $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, 'donations');
        try {
            $responseObj = $service->donations(new DonationPaymentRequest($request), $requestOptions);
            $response = $responseObj->toArray();
        } catch (AdyenException $e) {
            $response = ['error' => $e->getMessage()];
        }
        $this->adyenHelper->logResponse($response);

        return $response;
    }
}

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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionPaymentLinks extends BaseTransaction
{
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
     */
    public function __construct(
        Data $adyenHelper,
        Idempotency $idempotencyHelper
    ) {
        parent::__construct($adyenHelper);
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
        $requestOptions['headers'] = $this->requestHeaders($transferObject);

        $clientConfig = $transferObject->getClientConfig();
        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = new PaymentLinksApi($client);

        // If the payment links call is already done return the request
        if (!empty($request['resultCode'])) {
            // Initiate has already a response
            return $request;
        }

        $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
            $request,
            $requestOptions['headers']['idempotencyExtraData'] ?? null
        );

        $requestOptions['idempotencyKey'] = $idempotencyKey;

        $request['applicationInfo'] = $this->adyenHelper->buildApplicationInfo($client);

        $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/paymentLinks');
        try {
            $responseObj = $service->paymentLinks(new PaymentLinkRequest($request), $requestOptions);
            $response = $responseObj->toArray();
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
        }
        $this->adyenHelper->logResponse($response);

        return $response;
    }
}

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
use Adyen\Payment\Helper\PlatformInfo;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionPaymentLinks implements ClientInterface
{
    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * @var Idempotency
     */
    private Idempotency $idempotencyHelper;

    /**
     * @var PlatformInfo
     */
    private PlatformInfo $platformInfo;

    /**
     * @param Data $adyenHelper
     * @param Idempotency $idempotencyHelper
     * @param PlatformInfo $platformInfo
     */
    public function __construct(
        Data $adyenHelper,
        Idempotency $idempotencyHelper,
        PlatformInfo $platformInfo
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->idempotencyHelper = $idempotencyHelper;
        $this->platformInfo = $platformInfo;
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
        $idempotencyKeyExtraData = $headers['idempotencyExtraData'] ?? null;
        unset($headers['idempotencyExtraData']);
        $clientConfig = $transferObject->getClientConfig();

        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = $this->adyenHelper->initializePaymentLinksApi($client);

        // If the payment links call is already done return the request
        if (!empty($request['resultCode'])) {
            // Initiate has already a response
            return $request;
        }

        $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
            $request,
            $idempotencyKeyExtraData
        );

        $requestOptions['idempotencyKey'] = $idempotencyKey;
        $requestOptions['headers'] = $headers;
        $request['applicationInfo'] = $this->platformInfo->buildApplicationInfo($client);

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

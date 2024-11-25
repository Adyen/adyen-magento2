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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionCancel extends BaseTransaction
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
        Data        $adyenHelper,
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
        $requests = $transferObject->getBody();
        $requestOptions['headers'] = $this->requestHeaders($transferObject);

        $clientConfig = $transferObject->getClientConfig();
        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = $this->adyenHelper->initializeModificationsApi($client);
        $responseData = [];

        foreach ($requests as $request) {
            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $requestOptions['headers']['idempotencyExtraData'] ?? null
            );

            $requestOptions['idempotencyKey'] = $idempotencyKey;
            $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/cancels');
            $request['applicationInfo'] = $this->adyenHelper->buildApplicationInfo($client);
            $paymentCancelRequest = new PaymentCancelRequest($request);

            try {
                $response = $service->cancelAuthorisedPaymentByPspReference(
                    $request['paymentPspReference'],
                    $paymentCancelRequest,
                    $requestOptions
                );
                $responseData = $response->toArray();
                $this->adyenHelper->logResponse($responseData);
            } catch (AdyenException $e) {
                $responseData['error'] = $e->getMessage();
                $this->adyenHelper->logAdyenException($e);
            }
        }

        return $responseData;
    }
}

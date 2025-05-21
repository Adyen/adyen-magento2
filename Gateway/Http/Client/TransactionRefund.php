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
use Adyen\Payment\Helper\PlatformInfo;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Class TransactionSale
 */
class TransactionRefund implements TransactionRefundInterface
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
        $requests = $transferObject->getBody();
        $headers = $transferObject->getHeaders();
        $idempotencyKeyExtraData = $headers['idempotencyExtraData'];
        unset($headers['idempotencyExtraData']);
        $clientConfig = $transferObject->getClientConfig();

        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = $this->adyenHelper->initializeModificationsApi($client);
        $responses = [];

        foreach ($requests as $request) {
            $responseData = [];
            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $idempotencyKeyExtraData ?? null
            );
            $requestOptions['idempotencyKey'] = $idempotencyKey;
            $requestOptions['headers'] = $headers;

            $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/refunds');
            $request['applicationInfo'] = $this->platformInfo->buildApplicationInfo($client);
            $paymentRefundRequest = new PaymentRefundRequest($request);

            try {
                $response = $service->refundCapturedPayment(
                    $request['paymentPspReference'],
                    $paymentRefundRequest,
                    $requestOptions
                );
                $responseData = $response->toArray();
                // Add amount original reference and amount information to response
                $responseData[self::REFUND_AMOUNT] = $request['amount']['value'];
                $responseData[self::REFUND_CURRENCY] = $request['amount']['currency'];
                $responseData[self::ORIGINAL_REFERENCE] = $request['paymentPspReference'];
                $this->adyenHelper->logResponse($responseData);
            } catch (AdyenException $e) {
                $this->adyenHelper->logAdyenException($e);
                $responseData['error'] = $e->getMessage();
                $responseData['errorCode'] = $e->getAdyenErrorCode();
            }
            $responses[] = $responseData;
        }

        return $responses;
    }
}

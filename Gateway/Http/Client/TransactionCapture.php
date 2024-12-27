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
use Adyen\Model\Checkout\PaymentCaptureRequest;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Service\Checkout\ModificationsApi;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionCapture implements ClientInterface
{
    const MULTIPLE_AUTHORIZATIONS = 'multiple_authorizations';
    const FORMATTED_CAPTURE_AMOUNT = 'formatted_capture_amount';
    const CAPTURE_AMOUNT = 'capture_amount';
    const ORIGINAL_REFERENCE = 'paymentPspReference';
    const CAPTURE_RECEIVED = 'received';

    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * @var AdyenLogger
     */
    private AdyenLogger $adyenLogger;

    /**
     * @var Idempotency
     */
    private Idempotency $idempotencyHelper;

    /**
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     * @param Idempotency $idempotencyHelper
     */
    public function __construct(
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        Idempotency $idempotencyHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
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
        $idempotencyKeyExtraData = $headers['idempotencyExtraData'];
        unset($headers['idempotencyExtraData']);
        $clientConfig = $transferObject->getClientConfig();

        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = $this->adyenHelper->initializeModificationsApi($client);

        $requestOptions['headers'] = $headers;
        $request['applicationInfo'] = $this->adyenHelper->buildApplicationInfo($client);

        if (array_key_exists(self::MULTIPLE_AUTHORIZATIONS, $request)) {
            return $this->placeMultipleCaptureRequests($service, $request, $requestOptions);
        }

        $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
            $request,
            $idempotencyKeyExtraData ?? null
        );
        $requestOptions['idempotencyKey'] = $idempotencyKey;

        $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/captures');
        $paymentCaptureRequest = new PaymentCaptureRequest($request);
        $responseData = [];

        try {
            $response = $service->captureAuthorisedPayment(
                $request['paymentPspReference'],
                $paymentCaptureRequest,
                $requestOptions
            );

            $responseData = $response->toArray();
            $responseData = $this->copyParamsToResponse($responseData, $request);
            $this->adyenHelper->logResponse($responseData);
        } catch (AdyenException $e) {
            $this->adyenHelper->logAdyenException($e);
            $responseData['error'] = $e->getMessage();
        }

        return $responseData;
    }

    /**
     * @param ModificationsApi $service
     * @param array $requestContainer
     * @param array $requestOptions
     * @return array
     */
    private function placeMultipleCaptureRequests(
        ModificationsApi $service,
        array            $requestContainer,
        array            $requestOptions
    ): array {
        $response = [];
        $applicationInfo = $requestContainer['applicationInfo'];

        foreach ($requestContainer[self::MULTIPLE_AUTHORIZATIONS] as $request) {
            $idempotencyKeyExtraData = $request['idempotencyExtraData'];
            unset($request['idempotencyExtraData']);
            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $idempotencyKeyExtraData ?? null
            );

            $requestOptions['idempotencyKey'] = $idempotencyKey;
            try {
                // Copy merchant account from parent array to every request array
                $request[Requests::MERCHANT_ACCOUNT] = $requestContainer[Requests::MERCHANT_ACCOUNT];
                $request['applicationInfo'] = $applicationInfo;
                $paymentCaptureRequest = new PaymentCaptureRequest($request);
                $singleResponseObj = $service->captureAuthorisedPayment(
                    $request['paymentPspReference'],
                    $paymentCaptureRequest,
                    $requestOptions
                );

                $singleResponse = $singleResponseObj->toArray();
                $singleResponse[self::FORMATTED_CAPTURE_AMOUNT] = $request['amount']['currency'] . ' ' .
                    $this->adyenHelper->originalAmount(
                        $request['amount']['value'],
                        $request['amount']['currency']
                    );
                $singleResponse = $this->copyParamsToResponse($singleResponse, $request);
                $response[self::MULTIPLE_AUTHORIZATIONS][] = $singleResponse;
            } catch (AdyenException $e) {
                $pspReference = $request[OrderPaymentInterface::PSPREFRENCE] ?? 'pspReference not set';

                $message = sprintf(
                    'Exception occurred when attempting to capture multiple authorizations.
                    Authorization with pspReference %s: %s',
                    $pspReference,
                    $e->getMessage()
                );

                $this->adyenLogger->error($message);
                $response[self::MULTIPLE_AUTHORIZATIONS]['error'] = $message;
            }
        }

        return $response;
    }

    /**
     * @param array $response
     * @param array $request
     * @return array
     */
    private function copyParamsToResponse(array $response, array $request): array
    {
        $response[self::CAPTURE_AMOUNT] = $request['amount']['value'];
        $response[self::ORIGINAL_REFERENCE] = $request[self::ORIGINAL_REFERENCE];

        return $response;
    }
}

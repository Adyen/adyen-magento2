<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Model\Checkout\PaymentCaptureRequest;
use Adyen\Payment\Gateway\Validator\AbstractModificationsResponseValidator;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionCapture implements ClientInterface
{
    const CAPTURE_AMOUNT = 'amount';
    const CAPTURE_VALUE = 'value';
    const ORIGINAL_REFERENCE = 'paymentPspReference';

    /**
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     * @param Idempotency $idempotencyHelper
     */
    public function __construct(
        private readonly Data $adyenHelper,
        private readonly AdyenLogger $adyenLogger,
        private readonly Idempotency $idempotencyHelper
    ) { }

    /**
     * @param TransferInterface $transferObject
     * @return array
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $requestCollection = $transferObject->getBody();
        $headers = $transferObject->getHeaders();
        $clientConfig = $transferObject->getClientConfig();

        $client = $this->adyenHelper->initializeAdyenClientWithClientConfig($clientConfig);
        $service = $this->adyenHelper->initializeModificationsApi($client);

        $responseCollection = [];

        foreach ($requestCollection as $request) {
            $idempotencyKeyExtraData = $request['idempotencyExtraData'];
            unset($request['idempotencyExtraData']);
            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $idempotencyKeyExtraData ?? null
            );

            $request['applicationInfo'] = $this->adyenHelper->buildApplicationInfo($client);
            $requestOptions['headers'] = $headers;
            $requestOptions['idempotencyKey'] = $idempotencyKey;

            $paymentPspReference = $request['paymentPspReference'];
            unset($request['paymentPspReference']);

            try {
                $paymentCaptureRequest = new PaymentCaptureRequest($request);
                $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/captures');

                $response = $service->captureAuthorisedPayment(
                    $paymentPspReference,
                    $paymentCaptureRequest,
                    $requestOptions
                );

                $responseData = $response->toArray();
                $this->adyenHelper->logResponse($responseData);

                $responseCollection[] = $this->copyParamsToResponse($responseData, $request);
            } catch (AdyenException $e) {
                $message = sprintf(
                    "An error occurred during the capture attempt%s. %s",
                    !empty($paymentPspReference) ?
                        ' of authorisation with pspreference ' . $paymentPspReference :
                        '',
                    $e->getMessage()
                );

                $responseData['error'] = $message;

                $this->adyenLogger->error($message);
                $responseCollection[] = $responseData;
            }
        }

        return $responseCollection;
    }

    /**
     * @param array $response
     * @param array $request
     * @return array
     */
    private function copyParamsToResponse(array $response, array $request): array
    {
        $originalAmount = $this->adyenHelper->originalAmount(
            $request['amount']['value'],
            $request['amount']['currency']
        );

        $response[AbstractModificationsResponseValidator::FORMATTED_MODIFICATIONS_AMOUNT] = sprintf(
            "%s %s",
            $request['amount']['currency'],
            $originalAmount
        );

        return $response;
    }
}

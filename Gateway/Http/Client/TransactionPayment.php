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
use Adyen\Payment\Model\PaymentResponse;
use Adyen\Payment\Model\PaymentResponseFactory;
use Adyen\Payment\Model\ResourceModel\PaymentResponse as PaymentResponseResourceModel;
use Magento\Payment\Gateway\Http\ClientInterface;
use Adyen\Payment\Model\ApplicationInfo;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionPayment implements ClientInterface
{
    private Data $adyenHelper;
    private ApplicationInfo $applicationInfo;
    private PaymentResponseFactory $paymentResponseFactory;
    private PaymentResponseResourceModel $paymentResponseResourceModel;
    private Idempotency $idempotencyHelper;

    public function __construct(
        Data                         $adyenHelper,
        ApplicationInfo              $applicationInfo,
        PaymentResponseFactory       $paymentResponseFactory,
        PaymentResponseResourceModel $paymentResponseResourceModel,
        Idempotency                  $idempotencyHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->applicationInfo = $applicationInfo;
        $this->paymentResponseFactory = $paymentResponseFactory;
        $this->paymentResponseResourceModel = $paymentResponseResourceModel;
        $this->idempotencyHelper = $idempotencyHelper;
    }

    public function placeRequest(TransferInterface $transferObject): mixed
    {
        $request = $transferObject->getBody();
        $headers = $transferObject->getHeaders();
        $clientConfig = $transferObject->getClientConfig();

        // If the payments call is already done return the request
        if (!empty($request['resultCode'])) {
            //Initiate has already a response
            return $request;
        }

        //Check if it is a MOTO Transaction
        if(isset($clientConfig['isMotoTransaction']) && $clientConfig['isMotoTransaction'] === true) {
            $client = $this->adyenHelper->initializeAdyenClient(
                $clientConfig['storeId'],
                null,
                $request['merchantAccount']
            );
        } else {
            $client = $this->adyenHelper->initializeAdyenClient();
        }

        $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
            $request,
            $headers['idempotencyExtraData'] ?? null
        );
        $requestOptions['idempotencyKey'] = $idempotencyKey;
        $service = $this->adyenHelper->createAdyenCheckoutService($client);

        $this->adyenHelper->logRequest($request, Client::API_CHECKOUT_VERSION, '/payments');
        try {
            $response = $service->payments($request, $requestOptions);

            // Store the /payments response in the database in case it is needed in order to finish the payment
            /** @var PaymentResponse $paymentResponse */
            $paymentResponse = $this->paymentResponseFactory->create();
            $paymentResponse->setResponse(json_encode($response));
            $paymentResponse->setResultCode($response['resultCode']);
            $paymentResponse->setMerchantReference($request["reference"]);

            $this->paymentResponseResourceModel->save($paymentResponse);
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
            $response['errorCode'] = $e->getAdyenErrorCode();
        }
        $this->adyenHelper->logResponse($response);

        return $response;
    }
}

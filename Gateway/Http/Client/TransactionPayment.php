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
use Adyen\Payment\Model\PaymentResponse;
use Adyen\Payment\Model\PaymentResponseFactory;
use Adyen\Payment\Model\ResourceModel\PaymentResponse as PaymentResponseResourceModel;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Adyen\Payment\Model\ApplicationInfo;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionPayment implements ClientInterface
{
    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var ApplicationInfo
     */
    private $applicationInfo;

    /**
     * @var PaymentResponseFactory
     */
    private $paymentResponseFactory;

    /**
     * @var PaymentResponseResourceModel
     */
    private $paymentResponseResourceModel;

    /**
     * @var Idempotency
     */
    private $idempotencyHelper;

    /**
     * TransactionPayment constructor.
     * @param Data $adyenHelper
     * @param ApplicationInfo $applicationInfo
     * @param PaymentResponseFactory $paymentResponseFactory
     * @param PaymentResponseResourceModel $paymentResponseResourceModel
     * @param Idempotency $idempotencyHelper
     */
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

    /**
     * @param TransferInterface $transferObject
     * @return array|mixed|string
     * @throws AdyenException
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        $headers = $transferObject->getHeaders();

        // If the payments call is already done return the request
        if (!empty($request['resultCode'])) {
            //Initiate has already a response
            return $request;
        }

        $client = $this->adyenHelper->initializeAdyenClient();
        $service = $this->adyenHelper->createAdyenCheckoutService($client);

        $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
            $request,
            $headers['idempotencyExtraData'] ?? null
        );

        $requestOptions['idempotencyKey'] = $idempotencyKey;

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

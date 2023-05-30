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
use Adyen\ConnectionException;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\GiftcardPayment;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Model\PaymentResponse;
use Adyen\Payment\Model\PaymentResponseFactory;
use Adyen\Payment\Model\ResourceModel\PaymentResponse as PaymentResponseResourceModel;
use Adyen\Service\Checkout;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Store\Model\StoreManagerInterface;

class TransactionPayment implements ClientInterface
{
    private Data $adyenHelper;

    private PaymentResponseFactory $paymentResponseFactory;

    private PaymentResponseResourceModel $paymentResponseResourceModel;

    private Idempotency $idempotencyHelper;

    private OrdersApi $orderApiHelper;

    private StoreManagerInterface $storeManager;

    private ?int $remainingOrderAmount;

    /**
     * TransactionPayment constructor.
     * @param Data $adyenHelper
     * @param PaymentResponseFactory $paymentResponseFactory
     * @param PaymentResponseResourceModel $paymentResponseResourceModel
     * @param Idempotency $idempotencyHelper
     * @param OrdersApi $orderApiHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Data $adyenHelper,
        PaymentResponseFactory $paymentResponseFactory,
        PaymentResponseResourceModel $paymentResponseResourceModel,
        Idempotency $idempotencyHelper,
        OrdersApi $orderApiHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->paymentResponseFactory = $paymentResponseFactory;
        $this->paymentResponseResourceModel = $paymentResponseResourceModel;
        $this->idempotencyHelper = $idempotencyHelper;
        $this->orderApiHelper = $orderApiHelper;
        $this->storeManager = $storeManager;

        $this->remainingOrderAmount = null;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array|mixed|string
     * @throws AdyenException
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException|ConnectionException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        $headers = $transferObject->getHeaders();

        $client = $this->adyenHelper->initializeAdyenClient();
        $service = $this->adyenHelper->createAdyenCheckoutService($client);

        $this->remainingOrderAmount = $request['amount']['value'];

        // If the payments call is already done return the request
        if (!empty($request['resultCode'])) {
            //Initiate has already a response
            return $request;
        }

        if (isset($request['giftcardRequestParameters'])) {
            $redeemedGiftcards = $request['giftcardRequestParameters'];
            unset($request['giftcardRequestParameters']);

            try {
                $ordersResponse = $this->orderApiHelper->createOrder(
                    $request['reference'],
                    $request['amount']['value'],
                    $request['amount']['currency'],
                    $this->storeManager->getStore()->getId()
                );

                $response = $this->handleGiftcardPayments($request, $service, $redeemedGiftcards, $ordersResponse);

                $request['amount']['value'] = $this->remainingOrderAmount;
                $request['order'] = [
                    'pspReference' => $ordersResponse['pspReference'],
                    'orderData' => $ordersResponse['orderData']
                ];
            } catch (AdyenException $e) {
                $response['error'] = $e->getMessage();
                $response['errorCode'] = $e->getAdyenErrorCode();

                $this->adyenHelper->logResponse($response);

                return $response;
            }

            if ($this->remainingOrderAmount === 0) {
                return $response;
            }
        }

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

    /**
     * Returns the last /payments response to be used in the order.
     *
     * @param array $request
     * @param Checkout $service
     * @param array $redeemedGiftcards
     * @param array $ordersResponse
     * @return array
     * @throws AdyenException
     * @throws AlreadyExistsException
     */
    private function handleGiftcardPayments(
        array $request,
        Checkout $service,
        array $redeemedGiftcards,
        array $ordersResponse
    ): array {
        $response = [];

        foreach ($redeemedGiftcards as $giftcard) {
            $stateData = json_decode($giftcard['state_data'], true);

            if (!isset($stateData['paymentMethod']['type']) || $stateData['paymentMethod']['type'] !== 'giftcard') {
                continue;
            }

            if ($this->remainingOrderAmount > $stateData['giftcard']['balance']['value']) {
                $deductedAmount = $stateData['giftcard']['balance']['value'];
            } else {
                $deductedAmount = $this->remainingOrderAmount;
            }

            $giftcardPaymentRequest = $this->buildGiftcardPaymentRequest(
                $request,
                $ordersResponse,
                $stateData,
                $deductedAmount
            );

            $this->adyenHelper->logRequest(
                $giftcardPaymentRequest, Client::API_CHECKOUT_VERSION,
                '/payments'
            );

            $response = $service->payments($giftcardPaymentRequest);

            $this->adyenHelper->logResponse($response);

            /** @var PaymentResponse $paymentResponse */
            $paymentResponse = $this->paymentResponseFactory->create();
            $paymentResponse->setResponse(json_encode($response));
            $paymentResponse->setResultCode($response['resultCode']);
            $paymentResponse->setMerchantReference($request["reference"]);

            $this->paymentResponseResourceModel->save($paymentResponse);

            $this->remainingOrderAmount -= $deductedAmount;
        }

        return $response;
    }

    /**
     * @param array $request
     * @param array $orderData
     * @param array $stateData
     * @param int $amount
     * @return array
     */
    private function buildGiftcardPaymentRequest(
        array $request,
        array $orderData,
        array $stateData,
        int $amount
    ): array {
        $giftcardPaymentRequest = [];

        foreach (GiftcardPayment::validGiftcardPaymentRequestFields as $key) {
            if (isset($request[$key])) {
                $giftcardPaymentRequest[$key] = $request[$key];
            }
        }

        $giftcardPaymentRequest['paymentMethod'] = $stateData['paymentMethod'];
        $giftcardPaymentRequest['amount']['value'] = $amount;

        $giftcardPaymentRequest['order']['pspReference'] = $orderData['pspReference'];
        $giftcardPaymentRequest['order']['orderData'] = $orderData['orderData'];

        return $giftcardPaymentRequest;
    }
}

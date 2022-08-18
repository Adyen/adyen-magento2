<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Store\Model\StoreManagerInterface;

class TransactionPosCloudBackendSync implements ClientInterface
{
    /**
     * @var int
     */
    protected int $storeId;

    /**
     * @var Client
     */
    protected Client $client;

    /**
     * @var Data
     */
    protected Data $adyenHelper;

    /**
     * @var AdyenLogger
     */
    protected AdyenLogger $adyenLogger;

    public function __construct(
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        StoreManagerInterface $storeManager
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;

        $this->storeId = $storeManager->getStore()->getId();
        $apiKey = $this->adyenHelper->getPosApiKey($this->storeId);
        $client = $this->adyenHelper->initializeAdyenClient($this->storeId, $apiKey);
        $posTimeout = $this->adyenHelper->getAdyenPosCloudConfigData('pos_timeout', $this->storeId);
        if (!empty($posTimeout)) {
            $client->setTimeout($posTimeout);
        }

        $this->client = $client;
    }

    /**
     * Places request to gateway. In case of older implementation (using AdyenInitiateTerminalApi::initiate) parameters
     * will be obtained from the request. Otherwise, we will do the initiate call here, using initiatePosPayment()
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws LocalizedException|AdyenException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();

        $paymentServiceRequest = $request['paymentServiceRequest'];
        $transactionStatusRequest = $request['transactionStatusRequest'];
        $initiateDate = $request['initiateDate'];

        $statusDate = date("U");
        $totalTimeout = $this->adyenHelper->getAdyenPosCloudConfigData('total_timeout', $this->storeId);

        // Make sync payments call to Adyen
        $this->initiatePosPayment($paymentServiceRequest);

        $timeDiff = (int)$statusDate - (int)$initiateDate;
        if ($timeDiff > $totalTimeout) {
            throw new LocalizedException(__("POS connection timed out."));
        }

        return $this->checkPosTransactionStatus($transactionStatusRequest);
    }

    /**
     * Initiate a POS payment by sending a /sync call to Adyen
     *
     * @param array $request
     * @return void
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function initiatePosPayment(array $request): void
    {
        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LocalizedException(
                __('Terminal API initiate request was not a valid JSON')
            );
        }

        $service = $this->adyenHelper->createAdyenPosPaymentService($this->client);

        try {
            $response = $service->runTenderSync($request);
        } catch (AdyenException $e) {
            //Not able to perform a payment
            $this->adyenLogger->addAdyenDebug($response['error'] = $e->getMessage());
        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
            throw $e;
        }
    }

    /**
     * @param array $transactionStatusRequest
     * @return array
     * @throws AdyenException
     * @throws LocalizedException
     */
    public function checkPosTransactionStatus(array $transactionStatusRequest): array
    {
        $service = $this->adyenHelper->createAdyenPosPaymentService($this->client);
        $transactionStatusRequest['SaleToPOIRequest']['MessageHeader']['ServiceID'] = date("dHis");

        // Check the transaction status
        try {
            $response = $service->runTenderSync($transactionStatusRequest);
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
            return $response;
        }

        if (!empty($response['SaleToPOIResponse']['TransactionStatusResponse'])) {
            $statusResponse = $response['SaleToPOIResponse']['TransactionStatusResponse'];

            if ($statusResponse['Response']['Result'] == 'Failure') {
                $errorMsg = __('In Progress');
                throw new LocalizedException(__($errorMsg));
            }
            else {
                $paymentResponse = $statusResponse['RepeatedMessageResponse']['RepeatedResponseMessageBody']['PaymentResponse'];
            }
        }
        else {
            // probably SaleToPOIRequest, that means terminal unreachable, log the response as error
            $this->adyenLogger->addAdyenDebug(json_encode($response));
            throw new LocalizedException(__("The terminal could not be reached."));
        }

        return $paymentResponse;
    }
}

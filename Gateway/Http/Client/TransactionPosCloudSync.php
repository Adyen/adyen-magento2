<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */


namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\Util\Util;
use Magento\Payment\Gateway\Http\ClientInterface;

class TransactionPosCloudSync implements ClientInterface
{
    /**
     * @var int
     */
    protected $storeId;

    /**
     * @var \Adyen\Client
     */
    protected $client;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $adyenLogger;

    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->storeId = $storeManager->getStore()->getId();

        $apiKey = $this->adyenHelper->getPosApiKey($this->storeId);

        // initialize client
        $client = $this->adyenHelper->initializeAdyenClient($this->storeId, $apiKey);

        //Set configurable option in M2
        $posTimeout = $this->adyenHelper->getAdyenPosCloudConfigData('pos_timeout', $this->storeId);
        if (!empty($posTimeout)) {
            $client->setTimeout($posTimeout);
        }

        $this->client = $client;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        if (!empty($request['response']['SaleToPOIResponse']['PaymentResponse'])) {
            $paymentResponse = $request['response']['SaleToPOIResponse']['PaymentResponse'];
            //Initiate has already a response
            return $paymentResponse;
        }
        //always do status call and return the response of the status call
        $service = $this->adyenHelper->createAdyenPosPaymentService($this->client);

        $poiId = $request['terminalID'];
        $newServiceID = date("dHis");

        $statusDate = date("U");
        $timeDiff = (int)$statusDate - (int)$request['initiateDate'];

        $totalTimeout = $this->adyenHelper->getAdyenPosCloudConfigData('total_timeout', $this->storeId);
        if ($timeDiff > $totalTimeout) {
            throw new \Magento\Framework\Exception\LocalizedException(__("POS connection timed out."));
        }
        //Provide receipt to the shopper

        $request = [
            'SaleToPOIRequest' => [
                'MessageHeader' => [
                    'ProtocolVersion' => '3.0',
                    'MessageClass' => 'Service',
                    'MessageCategory' => 'TransactionStatus',
                    'MessageType' => 'Request',
                    'ServiceID' => $newServiceID,
                    'SaleID' => 'Magento2CloudStatus',
                    'POIID' => $poiId
                ],
                'TransactionStatusRequest' => [
                    'MessageReference' => [
                        'MessageCategory' => 'Payment',
                        'SaleID' => 'Magento2Cloud',
                        'ServiceID' => $request['serviceID']
                    ],
                    'DocumentQualifier' => [
                        "CashierReceipt",
                        "CustomerReceipt"
                    ],

                    'ReceiptReprintFlag' => true
                ]
            ]
        ];

        try {
            $response = $service->runTenderSync($request);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
            return $response;
        }

        if (!empty($response['SaleToPOIResponse']['TransactionStatusResponse'])) {
            $statusResponse = $response['SaleToPOIResponse']['TransactionStatusResponse'];
            if ($statusResponse['Response']['Result'] == 'Failure') {
                $errorMsg = __('In Progress');
                throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
            } else {
                $paymentResponse = $statusResponse['RepeatedMessageResponse']['RepeatedResponseMessageBody']
                ['PaymentResponse'];
            }
        } else {
            // probably SaleToPOIRequest, that means terminal unreachable, log the response as error
            $this->adyenLogger->error(json_encode($response));
            throw new \Magento\Framework\Exception\LocalizedException(__("The terminal could not be reached."));
        }

        return $paymentResponse;
    }
}

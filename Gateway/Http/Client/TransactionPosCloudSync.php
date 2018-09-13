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

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\RecurringType $recurringType,
        array $data = []
    ) {
        $this->_encryptor = $encryptor;
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->_recurringType = $recurringType;
        $this->_appState = $context->getAppState();

        // initialize client
        $apiKey = $this->_adyenHelper->getApiKey();
        $client = new \Adyen\Client();
        $client->setApplicationName("Magento 2 plugin");
        $client->setXApiKey($apiKey);
        $client->setTimeout(5);
        if ($this->_adyenHelper->isDemoMode()) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            $client->setEnvironment(\Adyen\Environment::LIVE);
        }
        // assign magento log
        $client->setLogger($adyenLogger);

        $this->_client = $client;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return array
     * @throws \Magento\Payment\Gateway\Http\ClientException
     * @throws \Magento\Payment\Gateway\Http\ConverterException
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        if (!empty($request['response'])) {
            //Initiate has already a response
            return $request['response'];
        }
        //always do status call and return the response of the status call
        $service = new \Adyen\Service\PosPayment($this->_client);

        $poiId = $this->_adyenHelper->getPoiId();
        $newServiceID = date("dHis");

        $statusDate = date("U");
        $timeDiff = (int)$statusDate - (int)$request['initiateDate'];

        $totalTimeout = $this->_adyenHelper->getAdyenPosCloudConfigData('total_timeout');
        if ($timeDiff > $totalTimeout) {
            throw new \Magento\Framework\Exception\LocalizedException(__("Pos Timeout."));
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
        }

        return $response;
    }
}
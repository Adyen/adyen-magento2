<?php
/**
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

use Adyen\AdyenException;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PointOfSale;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var Data
     */
    protected $adyenHelper;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /** @var Session */
    private $session;

    /** @var ChargedCurrency */
    private $chargedCurrency;

    /** @var PointOfSale */
    private $pointOfSale;

    public function __construct(
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        StoreManagerInterface $storeManager,
        Session $session,
        ChargedCurrency $chargedCurrency,
        PointOfSale $pointOfSale,
        array $data = []
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->session = $session;
        $this->pointOfSale = $pointOfSale;
        $this->chargedCurrency = $chargedCurrency;

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
     * Places request to gateway. In case of older implementation (using AdyenInitiateTerminalApi::initiate) parameters
     * will be obtained from the request. Otherwise we will do the initiate call here, using initiatePosPayment()
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws LocalizedException|AdyenException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();
        //always do status call and return the response of the status call
        $service = $this->adyenHelper->createAdyenPosPaymentService($this->client);
        $newServiceID = date("dHis");
        $statusDate = date("U");

        $terminalId = $request['terminalID'];

        if (array_key_exists('chainCalls', $request)) {
            $quote = $this->initiatePosPayment($terminalId, $request['numberOfInstallments']);
            $quoteInfoInstance = $quote->getPayment()->getMethodInstance()->getInfoInstance();
            $timeDiff = (int)$statusDate - (int)$quoteInfoInstance->getAdditionalInformation('initiateDate');
            $serviceId = $quoteInfoInstance->getAdditionalInformation('serviceID');
        } else {
            $timeDiff = (int)$statusDate - (int)$request['initiateDate'];
            $serviceId = $request['serviceID'];
        }


        $totalTimeout = $this->adyenHelper->getAdyenPosCloudConfigData('total_timeout', $this->storeId);
        if ($timeDiff > $totalTimeout) {
            throw new LocalizedException(__("POS connection timed out."));
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
                    'POIID' => $terminalId
                ],
                'TransactionStatusRequest' => [
                    'MessageReference' => [
                        'MessageCategory' => 'Payment',
                        'SaleID' => 'Magento2Cloud',
                        'ServiceID' => $serviceId
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
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
            return $response;
        }

        if (!empty($response['SaleToPOIResponse']['TransactionStatusResponse'])) {
            $statusResponse = $response['SaleToPOIResponse']['TransactionStatusResponse'];
            if ($statusResponse['Response']['Result'] == 'Failure') {
                $errorMsg = __('In Progress');
                throw new LocalizedException(__($errorMsg));
            } else {
                $paymentResponse = $statusResponse['RepeatedMessageResponse']['RepeatedResponseMessageBody']
                ['PaymentResponse'];
            }
        } else {
            // probably SaleToPOIRequest, that means terminal unreachable, log the response as error
            $this->adyenLogger->error(json_encode($response));
            throw new LocalizedException(__("The terminal could not be reached."));
        }

        return $paymentResponse;
    }

    /**
     * Initiate a POS payment by sending a /sync call to Adyen
     *
     * @param string $terminalId
     * @param string|null $numberOfInstallments
     * @return CartInterface
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function initiatePosPayment(string $terminalId, ?string $numberOfInstallments): CartInterface
    {

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LocalizedException(
                __('Terminal API initiate request was not a valid JSON')
            );
        }

        $poiId = $terminalId;

        /** @var CartInterface $quote */
        $quote = $this->session->getQuote();
        $payment = $quote->getPayment();
        $adyenAmountCurrency = $this->chargedCurrency->getQuoteAmountCurrency($quote);
        $payment->setMethod(AdyenPosCloudConfigProvider::CODE);
        $reference = $quote->reserveOrderId()->getReservedOrderId();

        $service = $this->adyenHelper->createAdyenPosPaymentService($this->client);
        $transactionType = \Adyen\TransactionType::NORMAL;

        $serviceID = date("dHis");
        $initiateDate = date("U");
        $timeStamper = date("Y-m-d") . "T" . date("H:i:s+00:00");

        $request = [
            'SaleToPOIRequest' =>
                [
                    'MessageHeader' =>
                        [
                            'MessageType' => 'Request',
                            'MessageClass' => 'Service',
                            'MessageCategory' => 'Payment',
                            'SaleID' => 'Magento2Cloud',
                            'POIID' => $poiId,
                            'ProtocolVersion' => '3.0',
                            'ServiceID' => $serviceID
                        ],
                    'PaymentRequest' =>
                        [
                            'SaleData' =>
                                [
                                    'TokenRequestedType' => 'Customer',
                                    'SaleTransactionID' =>
                                        [
                                            'TransactionID' => $reference,
                                            'TimeStamp' => $timeStamper
                                        ]
                                ],
                            'PaymentTransaction' =>
                                [
                                    'AmountsReq' =>
                                        [
                                            'Currency' => $adyenAmountCurrency->getCurrencyCode(),
                                            'RequestedAmount' => doubleval($adyenAmountCurrency->getAmount())
                                        ]
                                ]
                        ]
                ]
        ];

        if (isset($numberOfInstallments)) {
            $request['SaleToPOIRequest']['PaymentRequest']['PaymentData'] = [
                "PaymentType" => "Instalment",
                "Instalment" => [
                    "InstalmentType" => "EqualInstalments",
                    "SequenceNumber" => 1,
                    "Period" => 1,
                    "PeriodUnit" => "Monthly",
                    "TotalNbOfPayments" => intval($numberOfInstallments)
                ]
            ];

            $request['SaleToPOIRequest']['PaymentRequest']['PaymentTransaction']['TransactionConditions'] = [
                "DebitPreferredFlag" => false
            ];
        } else {
            $request['SaleToPOIRequest']['PaymentData'] = [
                'PaymentType' => $transactionType,
            ];
        }

        $request = $this->pointOfSale->addSaleToAcquirerData($request, $quote);
        $paymentInfoInstance = $quote->getPayment()->getMethodInstance()->getInfoInstance();

        $paymentInfoInstance->setAdditionalInformation(
            'serviceID',
            $serviceID
        );

        $paymentInfoInstance->setAdditionalInformation(
            'initiateDate',
            $initiateDate
        );

        try {
            $response = $service->runTenderSync($request);
        } catch (AdyenException $e) {
            //Not able to perform a payment
            $this->adyenLogger->addAdyenDebug("adyenexception");
            $response['error'] = $e->getMessage();
        } catch (\Exception $e) {
            //Probably timeout
            $paymentInfoInstance->setAdditionalInformation(
                'terminalResponse',
                null
            );
            $quote->save();
            $response['error'] = $e->getMessage();
            throw $e;
        }
        $paymentInfoInstance->setAdditionalInformation(
            'terminalResponse',
            $response
        );

        $quote->save();
        return $quote;
    }
}

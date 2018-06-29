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


namespace Adyen\Payment\Model;


use Adyen\Payment\Api\AdyenInitiateTerminalApiInterface;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Adyen\Util\Util;
use Magento\Payment\Gateway\Http\ClientInterface;

class AdyenInitiateTerminalApi implements AdyenInitiateTerminalApiInterface
{

    private $_encryptor;
    private $_adyenHelper;
    private $_adyenLogger;
    private $_recurringType;
    private $_appState;

    protected $_quoteRepository;

    /**
     * AdyenInitiateTerminalApi constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param RecurringType $recurringType
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\RecurringType $recurringType,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        array $data = []
    ) {
        $this->_encryptor = $encryptor;
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->_recurringType = $recurringType;
        $this->_appState = $context->getAppState();
        $this->_quoteRepository = $quoteRepository;

        // initialize client
        $apiKey = $this->_adyenHelper->getApiKey();
        $client = new \Adyen\Client();
        $client->setApplicationName("Magento 2 plugin");
        $client->setXApiKey($apiKey);

        //Set configurable option in M2
        $client->setTimeout(15);

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
     * Trigger sync call on terminal
     * @param string $quoteId
     * @return mixed
     */
    public function initiate($quoteId)
    {
        $quote = $this->_quoteRepository->getActive($quoteId);
        $payment = $quote->getPayment();
        $payment->setMethod(AdyenPosCloudConfigProvider::CODE);
        $reference = $quote->reserveOrderId()->getReservedOrderId();

        $service = new \Adyen\Service\PosPayment($this->_client);
        $transactionType = \Adyen\TransactionType::NORMAL;
        $poiId = $this->_adyenHelper->getPoiId();
        $serviceID = date("dHis");
        $timeStamper = date("Y-m-d") . "T" . date("H:i:s+00:00");

        $json = '{
                    "SaleToPOIRequest": {
                        "MessageHeader": {
                            "MessageType": "Request",
                            "MessageClass": "Service",
                            "MessageCategory": "Payment",
                            "SaleID": "Magento2Cloud",
                            "POIID": "' . $poiId . '",
                            "ProtocolVersion": "3.0",
                            "ServiceID": "' . $serviceID . '"
                        },
                        "PaymentRequest": {
                            "SaleData": {
                                "SaleTransactionID": {
                                    "TransactionID": "' . $reference . '",
                                    "TimeStamp": "' . $timeStamper . '"
                                },
                                "TokenRequestedType": "Customer",
                                "SaleReferenceID": "SalesRefABC"
                            },
                            "PaymentTransaction": {
                                "AmountsReq": {
                                    "Currency": "' . $quote->getCurrency()->getQuoteCurrencyCode() . '",
                                    "RequestedAmount": ' . $quote->getGrandTotal() . '
                                }
                            },
                            "PaymentData": {
                                "PaymentType": "' . $transactionType . '"
                            }
                        }
                    }
                }
            ';

        $params = json_decode($json, true); //Create associative array for passing along

        $quote->getPayment()->getMethodInstance()->getInfoInstance()->setAdditionalInformation('serviceID',
            $serviceID);

        try {
            $response = $service->runTenderSync($params);
        } catch (\Adyen\AdyenException $e) {
            //Not able to perform a payment
            $response['error'] = $e->getMessage();
        } catch (\Exception $e) {
            //Probably timeout
            $quote->save();
            $response['error'] = $e->getMessage();
            throw $e;
        }
        $quote->getPayment()->getMethodInstance()->getInfoInstance()->setAdditionalInformation('terminalResponse',
            $response);

        $quote->save();
        return $response;
    }
}
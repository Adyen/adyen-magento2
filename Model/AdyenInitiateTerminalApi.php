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

/**
 * Created by PhpStorm.
 * User: alessio
 * Date: 6/13/18
 * Time: 3:29 PM
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


        $this->_adyenLogger->addAdyenDebug("construct pos");
        // initialize client
        $apiKey = $this->_adyenHelper->getApiKey();
        $client = new \Adyen\Client();
        $client->setApplicationName("Magento 2 plugin");
        $client->setXApiKey($apiKey);
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
     * @param string $currency
     * @param int $amount
     * @return mixed
     */
    public function initiate($quoteId, $currency, $amount)
    {
        $quote = $this->_quoteRepository->getActive($quoteId);
        $payment = $quote->getPayment();
        $payment->setMethod(AdyenPosCloudConfigProvider::CODE);
        $amount = $payment->getAmount();
        $reference = $quote->reserveOrderId()->getReservedOrderId();
        $this->_adyenLogger->addAdyenDebug("reference: " . $reference);

        $this->_adyenLogger->addAdyenDebug("starting pos request");
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
                                    "Currency": "' . $currency . '",
                                    "RequestedAmount": ' . $amount . '
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
        try {
            $response = $service->runTenderSync($params);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        $quote->getPayment()->getMethodInstance()->getInfoInstance()->setAdditionalInformation('terminalResponse',
            $response);

        return $response;
    }
}
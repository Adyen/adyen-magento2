<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PointOfSale;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\Data\CartInterface;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Adyen\Payment\Helper\PaymentMethods;
use Magento\Framework\Exception\NoSuchEntityException;

class TransactionPosCloudSync implements ClientInterface
{
    /** @var int  */
    protected $storeId;

    /** @var int */
    protected $timeout;

    /** @var \Adyen\Client  */
    protected $client;

    /** @var Data  */
    protected $adyenHelper;

    /** @var AdyenLogger  */
    protected $adyenLogger;

    /** @var Config */
    protected $configHelper;

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
        Config $configHelper,
        array $data = []
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->session = $session;
        $this->pointOfSale = $pointOfSale;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;

        $this->storeId = $storeManager->getStore()->getId();

        $apiKey = $this->adyenHelper->getPosApiKey($this->storeId);

        // initialize client
        $client = $this->adyenHelper->initializeAdyenClient($this->storeId, $apiKey);

        //Set configurable option in M2
        $this->timeout = $this->configHelper->getAdyenPosCloudConfigData('total_timeout', $this->storeId);
        if (!empty($this->timeout)) {
            $client->setTimeout($this->timeout);
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


        $this->adyenHelper->logRequest($request, '', '/sync');
        try {
            $response = $service->runTenderSync($request);
        } catch (AdyenException $e) {
            //Not able to perform a payment
            $this->adyenLogger->addAdyenDebug($response['error'] = $e->getMessage());
        } catch (\Exception $e) {

        }
        $this->adyenHelper->logResponse($response);

        return $response;
    }
}

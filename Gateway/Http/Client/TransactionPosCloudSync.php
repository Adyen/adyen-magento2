<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Store\Model\StoreManagerInterface;

class TransactionPosCloudSync extends BaseTransaction
{
    protected int $storeId;
    protected mixed $timeout;
    protected Client $client;
    protected Data $adyenHelper;
    protected AdyenLogger $adyenLogger;
    protected Config $configHelper;

    public function __construct(
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        StoreManagerInterface $storeManager,
        Config $configHelper
    ) {
        parent::__construct($adyenHelper);
        $this->adyenLogger = $adyenLogger;
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

    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();
        $service = $this->adyenHelper->createAdyenPosPaymentService($this->client);

        $this->adyenHelper->logRequest($request, '', '/sync');
        try {
            $response = $service->runTenderSync($request);
        } catch (AdyenException $e) {
            //Not able to perform a payment
            $this->adyenLogger->addAdyenDebug($response['error'] = $e->getMessage());
        }

        $this->adyenHelper->logResponse($response);

        return $response;
    }
}

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

use Adyen\Client;
use Adyen\Exception\AuthenticationException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Store\Model\StoreManagerInterface;

class TransactionPosCloudSync implements ClientInterface
{
    /**
     * @deprecated Moved to initializeAdyenClientForPos()
     */
    protected mixed $timeout;
    /**
     * @deprecated Moved to method scope
     */
    protected Client $client;
    /**
     * @deprecated Moved to method scope
     */
    protected int $storeId;

    public function __construct(
        protected readonly Data $adyenHelper,
        protected readonly AdyenLogger $adyenLogger,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly Config $configHelper
    ) { }

    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();
        $this->adyenHelper->logRequest($request, '', '/sync');

        try {
            $storeId = $this->storeManager->getStore()->getId();
            $apiKey = $this->adyenHelper->getPosApiKey($storeId);

            if (empty($apiKey)) {
                throw new AuthenticationException(
                    'Required field POS API key is not configured! Check your Adyen configuration.'
                );
            }

            $client = $this->adyenHelper->initializeAdyenClientForPos($storeId, $apiKey);
            $service = $this->adyenHelper->createAdyenPosPaymentService($client);

            $response = $service->runTenderSync($request);
        } catch (Exception $e) {
            $this->adyenLogger->addAdyenDebug($response['error'] = $e->getMessage());
        }

        $this->adyenHelper->logResponse($response);

        return $response;
    }
}

<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Checkout\Model\Session;

class ConnectedTerminals
{
    /**
     * In-memory cache for the /connected-terminals response
     *
     * @var array|null
     */
    protected ?array $connectedTerminalsApiResponse = null;

    protected Session $session;
    protected Data $adyenHelper;
    private AdyenLogger $adyenLogger;

    public function __construct(
        Data $adyenHelper,
        Session $session,
        AdyenLogger $adyenLogger
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->session = $session;
        $this->adyenLogger = $adyenLogger;
    }

    public function getConnectedTerminals(int $storeId = null): array
    {
        if (!isset($storeId)) {
            $storeId = $this->session->getQuote()->getStoreId();
        }

        // initialize the adyen client
        $client = $this->adyenHelper->initializeAdyenClient($storeId, $this->adyenHelper->getPosApiKey($storeId));

        // initialize service
        $service = $this->adyenHelper->createAdyenPosPaymentService($client);

        $requestParams = [
            "merchantAccount" => $this->adyenHelper->getAdyenMerchantAccount('adyen_pos_cloud', $storeId),
        ];

        // In case the POS store id is set, provide in the request
        if (!empty($this->adyenHelper->getPosStoreId($storeId))) {
            $requestParams['store'] = $this->adyenHelper->getPosStoreId($storeId);
        }

        try {
            $this->adyenHelper->logRequest($requestParams, '', '/connectedTerminals');
            $responseData = $service->getConnectedTerminals($requestParams);
        } catch (AdyenException $e) {
            $this->adyenLogger->error(
                "The getConnectedTerminals response is empty check your Adyen configuration in Magento."
            );
            // return empty result
            return [];
        }
        $this->adyenHelper->logResponse($responseData);

        return $responseData;
    }

    /**
     * This method sets the /connected-terminals response in the in-memory cache.
     *
     * @param array $response
     * @return void
     */
    protected function setConnectedTerminalsApiResponse(array $response): void
    {
        $this->connectedTerminalsApiResponse = $response;
    }

    /**
     * This method returns the /connected-terminals response from the in-memory cache if it exists or calls the
     * getConnectedTerminals method to get the response and set it in the in-memory cache.
     *
     * @param int|null $storeId
     * @return array|null
     */
    public function getConnectedTerminalsApiResponse(?int $storeId = null): ?array
    {
        try {
            if (!isset($this->connectedTerminalsApiResponse)) {
                $this->setConnectedTerminalsApiResponse($this->getConnectedTerminals($storeId));
            }
        } catch (Exception $e) {
            $this->adyenLogger->error(
                sprintf("An error occurred while trying to get connected terminals: %s", $e->getMessage())
            );
        }

        return $this->connectedTerminalsApiResponse;
    }
}

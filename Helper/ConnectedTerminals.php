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
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class ConnectedTerminals
{
    /**
     * @param Data $adyenHelper
     * @param Session $session
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        protected readonly Data $adyenHelper,
        protected readonly Session $session,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param int|null $storeId
     * @return array
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConnectedTerminals(?int $storeId = null): array
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
}

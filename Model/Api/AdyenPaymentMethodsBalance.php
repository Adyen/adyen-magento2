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

namespace Adyen\Payment\Model\Api;

use Adyen\AdyenException;
use Adyen\Model\Checkout\BalanceCheckRequest;
use Adyen\Payment\Api\AdyenPaymentMethodsBalanceInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManager;

class AdyenPaymentMethodsBalance implements AdyenPaymentMethodsBalanceInterface
{
    const FAILED_RESULT_CODE = 'Failed';

    /**
     * @var Json
     */
    private Json $jsonSerializer;

    /**
     * @var StoreManager
     */
    private StoreManager $storeManager;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * @var AdyenLogger
     */
    private AdyenLogger $adyenLogger;

    /**
     * @param Json $jsonSerializer
     * @param StoreManager $storeManager
     * @param Config $config
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Json $jsonSerializer,
        StoreManager $storeManager,
        Config $config,
        Data $adyenHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param string $payload
     * @return string
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function getBalance(string $payload): string
    {
        $payload = $this->jsonSerializer->unserialize($payload);
        $storeId = $this->storeManager->getStore()->getId();

        $payload['merchantAccount'] = $this->config->getMerchantAccount($storeId);

        try {
            $client = $this->adyenHelper->initializeAdyenClient($storeId);
            $service = $this->adyenHelper->initializeOrdersApi($client);
            $response = $service->getBalanceOfGiftCard(new BalanceCheckRequest($payload));

            if ($response->getResultCode() === self::FAILED_RESULT_CODE) {
                // Balance endpoint doesn't send HTTP status 422 for invalid PIN, manual handling required.
                $errorMessage = $response['additionalData']['acquirerResponseCode'] ?? 'Unknown error!';
                throw new AdyenException($errorMessage);
            }

            return json_encode($response->jsonSerialize());
        } catch (AdyenException $e) {
            $this->adyenLogger->error(
                sprintf("An error occurred during balance check! %s", $e->getMessage())
            );

            throw $e;
        }
    }
}

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
use Adyen\Payment\Api\AdyenPaymentMethodsBalanceInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManager;

class AdyenPaymentMethodsBalance implements AdyenPaymentMethodsBalanceInterface
{
    const FAILED_RESULT_CODE = 'Failed';

    private Json $jsonSerializer;
    private StoreManager $storeManager;
    private Config $config;
    private Data $adyenHelper;
    private AdyenLogger $adyenLogger;

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

    public function getBalance(string $payload): string
    {
        $payload = $this->jsonSerializer->unserialize($payload);
        $storeId = $this->storeManager->getStore()->getId();

        $payload['merchantAccount'] = $this->config->getMerchantAccount($storeId);

        try {
            $client = $this->adyenHelper->initializeAdyenClient($storeId);
            $service = $this->adyenHelper->createAdyenCheckoutService($client);

            $response = $service->paymentMethodsBalance($payload);

            if ($response['resultCode'] === self::FAILED_RESULT_CODE) {
                // Balance endpoint doesn't send HTTP status 422 for invalid PIN, manual handling required.
                $errorMessage = $response['additionalData']['acquirerResponseCode'] ?? 'Unknown error!';
                throw new AdyenException($errorMessage);
            }

            return json_encode($response);
        } catch (AdyenException $e) {
            $this->adyenLogger->error(
                sprintf("An error occurred during balance check! %s", $e->getMessage())
            );

            throw $e;
        }
    }
}

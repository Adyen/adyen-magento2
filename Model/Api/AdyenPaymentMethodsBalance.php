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
use Adyen\Payment\Helper\OrdersApi;
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
     * @var OrdersApi
     */
    private OrdersApi $ordersApiHelper;

    /**
     * @param Json $jsonSerializer
     * @param StoreManager $storeManager
     * @param OrdersApi $ordersApiHelper
     */
    public function __construct(
        Json $jsonSerializer,
        StoreManager $storeManager,
        OrdersApi $ordersApiHelper
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->storeManager = $storeManager;
        $this->ordersApiHelper = $ordersApiHelper;
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
        $storeId = (int) $this->storeManager->getStore()->getId();

        $response = $this->ordersApiHelper->getGiftcardBalance($payload, $storeId);

        return json_encode($response->jsonSerialize());
    }
}

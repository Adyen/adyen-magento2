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

use Adyen\Payment\Api\AdyenGiftcardInterface;
use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;

class AdyenGiftcard implements AdyenGiftcardInterface
{
    private StateDataCollection $adyenStateData;

    /**
     * @param StateDataCollection $adyenStateData
     */
    public function __construct(
        StateDataCollection $adyenStateData
    ) {
        $this->adyenStateData = $adyenStateData;
    }

    /**
     * @param string $quoteId
     * @return string
     */
    public function getRedeemedGiftcards(string $quoteId): string
    {
        $stateDataArray = $this->adyenStateData->getStateDataRowsWithQuoteId($quoteId);

        return json_encode($this->filterGiftcardStateData($stateDataArray->getData()));
    }

    /**
     * @param array $stateDataArray
     * @return array
     */
    private function filterGiftcardStateData(array $stateDataArray): array
    {
        $responseArray = [];

        foreach ($stateDataArray as $key => $item) {
            $stateData = json_decode($item['state_data'], true);
            if (!isset($stateData['paymentMethod']['type']) ||
                !isset($stateData['paymentMethod']['brand']) ||
                $stateData['paymentMethod']['type'] !== 'giftcard') {
                unset($stateDataArray[$key]);
                continue;
            }

            $responseArray[] = [
                'stateDataId' => $item['entity_id'],
                'brand' => $stateData['paymentMethod']['brand'],
                'title' => $stateData['giftcard']['title'],
                'balance' => $stateData['giftcard']['balance'],
                'deductedBalance' => 0
            ];
        }

        return $responseArray;
    }
}

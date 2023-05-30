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
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

class AdyenGiftcard implements AdyenGiftcardInterface
{
    private StateDataCollection $adyenStateData;
    private CartRepositoryInterface $quoteRepository;
    private Data $adyenHelper;

    /**
     * @param StateDataCollection $adyenStateData
     */
    public function __construct(
        StateDataCollection $adyenStateData,
        CartRepositoryInterface $quoteRepository,
        Data $adyenHelper
    ) {
        $this->adyenStateData = $adyenStateData;
        $this->quoteRepository = $quoteRepository;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param string $quoteId
     * @return string
     */
    public function getRedeemedGiftcards(string $quoteId): string
    {
        $stateDataArray = $this->adyenStateData->getStateDataRowsWithQuoteId($quoteId, 'ASC');
        $quote = $this->quoteRepository->get($quoteId);

        return json_encode($this->filterGiftcardStateData($stateDataArray->getData(), $quote));
    }

    /**
     * @param array $stateDataArray
     * @return array
     */
    private function filterGiftcardStateData(array $stateDataArray, CartInterface $quote): array
    {
        $responseArray = [];

        $remainingAmount = $this->adyenHelper->formatAmount(
            $quote->getGrandTotal(),
            $quote->getCurrency()->getQuoteCurrencyCode()
        );

        foreach ($stateDataArray as $key => $item) {
            $stateData = json_decode($item['state_data'], true);
            if (!isset($stateData['paymentMethod']['type']) ||
                !isset($stateData['paymentMethod']['brand']) ||
                $stateData['paymentMethod']['type'] !== 'giftcard') {
                unset($stateDataArray[$key]);
                continue;
            }

            if ($remainingAmount > $stateData['giftcard']['balance']['value']) {
                $deductedAmount = $stateData['giftcard']['balance']['value'];
            } else {
                $deductedAmount = $remainingAmount;
            }

            $responseArray[] = [
                'stateDataId' => $item['entity_id'],
                'brand' => $stateData['paymentMethod']['brand'],
                'title' => $stateData['giftcard']['title'],
                'balance' => $stateData['giftcard']['balance'],
                'deductedAmount' => $this->adyenHelper->originalAmount(
                    $deductedAmount,
                    $quote->getCurrency()->getQuoteCurrencyCode()
                )
            ];

            $remainingAmount -= $deductedAmount;
        }

        return $responseArray;
    }
}

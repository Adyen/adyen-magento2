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
use Adyen\Payment\Helper\GiftcardPayment;
use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Magento\Framework\Pricing\Helper\Data as PricingData;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

class AdyenGiftcard implements AdyenGiftcardInterface
{
    private StateDataCollection $adyenStateData;
    private CartRepositoryInterface $quoteRepository;
    private Data $adyenHelper;
    private GiftcardPayment $giftcardPaymentHelper;
    private PricingData $pricingDataHelper;

    public function __construct(
        StateDataCollection $adyenStateData,
        CartRepositoryInterface $quoteRepository,
        Data $adyenHelper,
        GiftcardPayment $giftcardPaymentHelper,
        PricingData $pricingDataHelper
    ) {
        $this->adyenStateData = $adyenStateData;
        $this->quoteRepository = $quoteRepository;
        $this->adyenHelper = $adyenHelper;
        $this->giftcardPaymentHelper = $giftcardPaymentHelper;
        $this->pricingDataHelper = $pricingDataHelper;
    }

    public function getRedeemedGiftcards(string $quoteId): string
    {
        $stateDataArray = $this->adyenStateData->getStateDataRowsWithQuoteId($quoteId, 'ASC');
        $quote = $this->quoteRepository->get($quoteId);

        $currency = $quote->getQuoteCurrencyCode();
        $formattedOrderAmount = $this->adyenHelper->formatAmount(
            $quote->getGrandTotal(),
            $currency
        );
        $giftcardDiscount = $this->giftcardPaymentHelper->getQuoteGiftcardDiscount($quote);

        $totalDiscount = $this->pricingDataHelper->currency(
            $this->adyenHelper->originalAmount(
                $giftcardDiscount,
                $currency
            ),
            $currency,
            false
        );

        $remainingOrderAmount = $this->pricingDataHelper->currency(
            $this->adyenHelper->originalAmount(
                $formattedOrderAmount - $giftcardDiscount,
                $currency
            ),
            $currency,
            false
        );

        $response = [
            'redeemedGiftcards' => $this->filterGiftcardStateData($stateDataArray->getData(), $quote),
            'remainingAmount' => $remainingOrderAmount,
            'totalDiscount' => $totalDiscount
        ];

        return json_encode($response);
    }

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
                'deductedAmount' =>  $this->pricingDataHelper->currency(
                    $this->adyenHelper->originalAmount(
                        $deductedAmount,
                        $quote->getCurrency()->getQuoteCurrencyCode()
                    ),
                    $quote->getCurrency()->getQuoteCurrencyCode(),
                    false
                )
            ];

            $remainingAmount -= $deductedAmount;
        }

        return $responseArray;
    }
}

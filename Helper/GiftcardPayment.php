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

use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Magento\Quote\Api\Data\CartInterface;

class GiftcardPayment
{
    const VALID_GIFTCARD_REQUEST_FIELDS = [
        'merchantAccount',
        'shopperReference',
        'shopperEmail',
        'telephoneNumber',
        'shopperName',
        'countryCode',
        'shopperLocale',
        'shopperIP',
        'billingAddress',
        'deliveryAddress',
        'amount',
        'reference',
        'additionalData',
        'fraudOffset',
        'browserInfo',
        'shopperInteraction',
        'returnUrl',
        'channel',
        'origin'
    ];

    private StateDataCollection $adyenStateData;
    private Data $adyenHelper;

    public function __construct(
        StateDataCollection $adyenStateData,
        Data $adyenHelper
    ) {
        $this->adyenStateData = $adyenStateData;
        $this->adyenHelper = $adyenHelper;
    }

    public function buildGiftcardPaymentRequest(
        array $request,
        array $orderData,
        array $stateData,
        int $amount
    ): array {
        $giftcardPaymentRequest = [];

        foreach (self::VALID_GIFTCARD_REQUEST_FIELDS as $key) {
            if (isset($request[$key])) {
                $giftcardPaymentRequest[$key] = $request[$key];
            }
        }

        $giftcardPaymentRequest['paymentMethod'] = $stateData['paymentMethod'];
        $giftcardPaymentRequest['amount']['value'] = $amount;

        $giftcardPaymentRequest['order']['pspReference'] = $orderData['pspReference'];
        $giftcardPaymentRequest['order']['orderData'] = $orderData['orderData'];

        return $giftcardPaymentRequest;
    }

    public function getQuoteGiftcardDiscount(CartInterface $quote): int
    {
        $formattedOrderAmount = $this->adyenHelper->formatAmount(
            $quote->getGrandTotal(),
            $quote->getCurrency()
        );

        $totalGiftcardBalance = $this->getQuoteGiftcardTotalBalance($quote->getId());

        if ($totalGiftcardBalance > 0) {
            if ($totalGiftcardBalance > $formattedOrderAmount) {
                return $formattedOrderAmount;
            } else {
                return $totalGiftcardBalance;
            }
        } else {
            return 0;
        }
    }

    public function getQuoteGiftcardTotalBalance(int $quoteId): int
    {
        $stateDataArray = $this->adyenStateData->getStateDataRowsWithQuoteId($quoteId);
        $totalBalance = 0;

        foreach ($stateDataArray as $stateData) {
            $stateData = json_decode($stateData['state_data'], true);

            if (isset($stateData['paymentMethod']['type']) ||
                isset($stateData['paymentMethod']['brand']) ||
                $stateData['paymentMethod']['type'] === 'giftcard') {
                $totalBalance += $stateData['giftcard']['balance']['value'];
            }
        }

        return $totalBalance;
    }
}

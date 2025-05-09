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
use Adyen\Payment\Api\GuestAdyenGiftcardInterface;
use Adyen\Payment\Helper\GiftcardPayment;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GuestAdyenGiftcard implements GuestAdyenGiftcardInterface
{
    /**
     * @param GiftcardPayment $giftcardPaymentHelper
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly GiftcardPayment $giftcardPaymentHelper,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param string $cartId
     * @return string
     * @throws AdyenException
     */
    public function getRedeemedGiftcards(string $cartId): string
    {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        } catch (NoSuchEntityException $e) {
            $errorMessage = sprintf("Quote with masked ID %s not found!", $cartId);
            $this->adyenLogger->error($errorMessage);

            throw new AdyenException(__('Error with payment method please select different payment method.'));
        }

        return $this->giftcardPaymentHelper->fetchRedeemedGiftcards($quoteId);
    }
}

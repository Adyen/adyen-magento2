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

use Adyen\Payment\Api\GuestAdyenGiftcardInterface;
use Adyen\Payment\Helper\GiftcardPayment;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestAdyenGiftcard implements GuestAdyenGiftcardInterface
{
    private GiftcardPayment $giftcardPaymentHelper;
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    public function __construct(
        GiftcardPayment $giftcardPaymentHelper,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->giftcardPaymentHelper = $giftcardPaymentHelper;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    public function getRedeemedGiftcards(string $cartId): string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        return $this->giftcardPaymentHelper->fetchRedeemedGiftcards($quoteId);
    }
}

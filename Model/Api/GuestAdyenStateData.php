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

use Adyen\Payment\Api\GuestAdyenStateDataInterface;
use Adyen\Payment\Helper\StateData as StateDataHelper;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestAdyenStateData implements GuestAdyenStateDataInterface
{
    private StateDataHelper $stateDataHelper;
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    public function __construct(
        StateDataHelper $stateDataHelper,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->stateDataHelper = $stateDataHelper;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    public function save(string $stateData, string $cartId): void
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        $this->stateDataHelper->saveStateData($stateData, $quoteId);
    }

    public function remove(int $stateDataId, string $cartId): bool
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        return $this->stateDataHelper->removeStateData($stateDataId, $quoteId);
    }
}

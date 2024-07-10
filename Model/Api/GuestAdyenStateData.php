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
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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

    /**
     * @param string $stateData
     * @param string $cartId
     * @return int
     * @throws InputException
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    public function save(string $stateData, string $cartId): int
    {
        $quoteId = $this->getQuoteIdFromMaskedCartId($cartId);
        $stateData = $this->stateDataHelper->saveStateData($stateData, $quoteId);

        return $stateData->getEntityId();
    }

    /**
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function remove(int $stateDataId, string $cartId): bool
    {
        $quoteId = $this->getQuoteIdFromMaskedCartId($cartId);

        return $this->stateDataHelper->removeStateData($stateDataId, $quoteId);
    }

    /**
     * @throws InputException
     */
    private function getQuoteIdFromMaskedCartId(string $cartId): int
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        if (is_null($quoteId)) {
            $errorMessage = __("An error occurred: missing required parameter :cartId!");
            throw new InputException($errorMessage);
        }

        return $quoteId;
    }
}

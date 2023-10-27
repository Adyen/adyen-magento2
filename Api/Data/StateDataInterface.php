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

namespace Adyen\Payment\Api\Data;

interface StateDataInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    const ENTITY_ID = 'entity_id';
    const QUOTE_ID = 'quote_id';
    const STATE_DATA = 'state_data';

    public function getEntityId();

    public function setEntityId($entityId);

    /**
     * @return int
     */
    public function getQuoteId(): int;

    /**
     * @param int $quoteId
     * @return StateDataInterface
     */
    public function setQuoteId(int $quoteId): StateDataInterface;

    /**
     * @return string|null
     */
    public function getStateData(): ?string;

    /**
     * @param string $stateData
     * @return StateDataInterface
     */
    public function setStateData(string $stateData): StateDataInterface;
}

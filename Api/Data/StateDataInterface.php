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

    /*
     * Entity ID.
     */
    const ENTITY_ID = 'entity_id';

    /*
     * Quote ID.
     */
    const QUOTE_ID = 'quote_id';

    /*
     * Payment State Data.
     */
    const STATE_DATA = 'state_data';

    /**
     * Cannot use PHP typing due to Magento constraints
     *
     * @return int|null Entity ID.
     */
    public function getEntityId();

    /**
     * Cannot use PHP typing due to Magento constraints
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    public function getQuoteId(): int;

    public function setQuoteId(int $quoteId): StateDataInterface;

    public function getStateData(): ?string;

    public function setStateData(string $stateData): StateDataInterface;
}

<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
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
     * Gets the ID for the state data.
     *
     * @return int|null Entity ID.
     */
    public function getEntityId();

    /**
     * Sets entity ID.
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);


    /**
     * Gets the quote ID for the state data.
     *
     * @return int|null Quote ID.
     */
    public function getQuoteId();

    /**
     * Sets quote ID.
     *
     * @param int $quoteId
     * @return $this
     */
    public function setQuoteId($quoteId);

    /**
     * Gets the state data.
     *
     * @return string|null State Data.
     */
    public function getStateData();

    /**
     * Sets state data
     *
     * @param string $stateData
     * @return $this
     */
    public function setStateData($stateData);

}

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


interface AdditionalInformationInterface
{
    const ENTITY_ID = 'entity_id';

    const PAYMENT_ID = 'payment_id';

    const ADDITIONAL_INFORMATION = 'additional_information';

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
    public function setEntityId(int $entityId);

    /**
     * Gets the ID for the state data.
     *
     * @return int|null Payment ID.
     */
    public function getPaymentId();

    /**
     * Sets Payment ID.
     *
     * @param int $paymentId
     * @return $this
     */
    public function setPaymentId(int $paymentId);

    /**
     * Gets the payment additionalInformation.
     *
     * @return string|null Payment AdditionalInformation.
     */
    public function getAdditionalInformation();

    /**
     * Sets payment additionalInformation.
     *
     * @param string $additionalInformation
     * @return $this
     */
    public function setAdditionalInformation(string $additionalInformation);
    
}
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

interface PaymentResponseInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */

    /*
     * Entity ID.
     */
    const ENTITY_ID = 'entity_id';

    /*
     * Merchant reference ID.
     */
    const MERCHANT_REFERENCE = 'merchant_reference';

    /*
     * Payment Response Result Code.
     */
    const RESULT_CODE = 'result_code';

    /*
     * Payment Response.
     */
    const RESPONSE = 'response';

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
     * Gets the merchant reference for the payment response
     *
     * @return string|null Merchant Reference.
     */
    public function getMerchantReference();

    /**
     * Sets merchant reference.
     *
     * @param string $merchantReference
     * @return $this
     */
    public function setMerchantReference($merchantReference);

    /**
     * Gets the result code.
     *
     * @return string|null Result Code.
     */
    public function getResultCode();

    /**
     * Sets result code.
     *
     * @param string $resultCode
     * @return $this
     */
    public function setResultCode($resultCode);

    /**
     * Gets the payment response.
     *
     * @return string|null Payment Response.
     */
    public function getResponse();

    /**
     * Sets payment response.
     *
     * @param string $response
     * @return $this
     */
    public function setResponse($response);

}

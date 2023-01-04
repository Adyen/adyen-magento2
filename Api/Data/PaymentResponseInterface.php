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
     * @return int|null Entity ID.
     */
    public function getEntityId();

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    public function getMerchantReference(): ?string;

    public function setMerchantReference(string $merchantReference): PaymentResponseInterface;

    public function getResultCode(): ?string;

    public function setResultCode(string $resultCode): PaymentResponseInterface;

    public function getResponse(): ?string;

    public function setResponse(string $response): PaymentResponseInterface;

}

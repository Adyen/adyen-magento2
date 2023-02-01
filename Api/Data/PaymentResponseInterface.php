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
    const ENTITY_ID = 'entity_id';
    const MERCHANT_REFERENCE = 'merchant_reference';
    const RESULT_CODE = 'result_code';
    const RESPONSE = 'response';

    public function getEntityId(): ?int;

    public function setEntityId(int $entityId): PaymentResponseInterface;

    public function getMerchantReference(): ?string;

    public function setMerchantReference(string $merchantReference): PaymentResponseInterface;

    public function getResultCode(): ?string;

    public function setResultCode(string $resultCode): PaymentResponseInterface;

    public function getResponse(): ?string;

    public function setResponse(string $response): PaymentResponseInterface;

}

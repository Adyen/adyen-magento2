<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Data;

use DateTime;

interface CreditmemoInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    const ENTITY_ID = 'entity_id';
    const PSPREFERENCE = 'pspreference';
    const ORIGINAL_REFERENCE = 'original_reference';

    const CREDITMEMO_ID = 'creditmemo_id';
    const ADYEN_ORDER_PAYMENT_ID = 'adyen_order_payment_id';
    const AMOUNT = 'amount';
    const STATUS = 'status';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * @return int|null Entity ID.
     */
    public function getEntityId();

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    public function getPspreference(): ?string;

    public function setPspreference(string $pspReference): CreditmemoInterface;

    public function getOriginalReference(): ?string;

    public function setOriginalReference(string $originalReference): CreditmemoInterface;

    public function getCreditmemoId(): ?int;

    public function setCreditmemoId(int $creditMemoId): CreditmemoInterface;

    public function getAmount(): ?int;

    public function setAmount(int $amount): CreditmemoInterface;

    public function getAdyenPaymentOrderId(): ?int;

    public function setAdyenPaymentOrderId(int $id): CreditmemoInterface;

    public function getStatus(): ?string;

    public function setStatus(string $status): CreditmemoInterface;

    public function getCreatedAt(): ?DateTime;

    public function setCreatedAt(DateTime $createdAt): CreditmemoInterface;

    public function getUpdatedAt(): ?DateTime;

    public function setUpdatedAt(DateTime $updatedAt): CreditmemoInterface;
}

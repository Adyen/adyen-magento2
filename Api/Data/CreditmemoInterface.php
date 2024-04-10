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
    const WAITING_FOR_WEBHOOK_STATUS= 'WAITING_FOR_WEBHOOK';
    const FAILED_STATUS = 'FAILED';
    const COMPLETED_STATUS = 'COMPLETED';

    public function getEntityId();

    public function setEntityId($entityId);

    /**
     * @return string|null
     */
    public function getPspreference(): ?string;

    /**
     * @param string $pspReference
     * @return CreditmemoInterface
     */
    public function setPspreference(string $pspReference): CreditmemoInterface;

    /**
     * @return string|null
     */
    public function getOriginalReference(): ?string;

    /**
     * @param string $originalReference
     * @return CreditmemoInterface
     */
    public function setOriginalReference(string $originalReference): CreditmemoInterface;

    /**
     * @return int|null
     */
    public function getCreditmemoId(): ?int;

    /**
     * @param int $creditMemoId
     * @return CreditmemoInterface
     */
    public function setCreditmemoId(int $creditMemoId): CreditmemoInterface;

    /**
     * @return float|null
     */
    public function getAmount(): ?float;

    /**
     * @param float $amount
     * @return CreditmemoInterface
     */
    public function setAmount(float $amount): CreditmemoInterface;

    /**
     * @return int|null
     */
    public function getAdyenPaymentOrderId(): ?int;

    /**
     * @param int $id
     * @return CreditmemoInterface
     */
    public function setAdyenPaymentOrderId(int $id): CreditmemoInterface;

    /**
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * @param string $status
     * @return CreditmemoInterface
     */
    public function setStatus(string $status): CreditmemoInterface;

    /**
     * @return DateTime|null
     */
    public function getCreatedAt(): ?DateTime;

    /**
     * @param DateTime $createdAt
     * @return CreditmemoInterface
     */
    public function setCreatedAt(DateTime $createdAt): CreditmemoInterface;

    /**
     * @return DateTime|null
     */
    public function getUpdatedAt(): ?DateTime;

    /**
     * @param DateTime $updatedAt
     * @return CreditmemoInterface
     */
    public function setUpdatedAt(DateTime $updatedAt): CreditmemoInterface;
}

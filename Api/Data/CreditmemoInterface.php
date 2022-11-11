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
     * Gets the ID for the creditmemo.
     *
     * @return int|null Entity ID.
     */
    public function getEntityId(): ?int;

    /**
     * Sets entity ID.
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId): CreditmemoInterface;

    /**
     * Gets the Pspreference for the creditmemo(capture)
     *
     * @return string|null Pspreference.
     */
    public function getPspreference(): ?string;

    /**
     * Sets Pspreference.
     *
     * @param string $pspreference
     * @return $this
     */
    public function setPspreference(string $pspreference): CreditmemoInterface;

    /**
     * @return string
     */
    public function getOriginalReference(): string;

    /**
     * @param  string $originalReference
     * @return $this
     */
    public function setOriginalReference(string $originalReference): CreditmemoInterface;

    /**
     * Gets the CreditmemoID for the creditmemo.
     *
     * @return int|null Creditmemo ID.
     */
    public function getCreditmemoId(): ?int;

    /**
     * @param int $creditMemoId
     * @return $this
     */
    public function setCreditmemoId(int $creditmemoId): CreditmemoInterface;

    /**
     * @return int|null
     */
    public function getAmount(): ?int;

    /**
     * @param int $amount
     * @return $this
     */
    public function setAmount(int $amount): CreditmemoInterface;

    /**
     * @return int|null
     */
    public function getAdyenPaymentOrderId(): ?int;

    /**
     * @param int $id
     * @return $this
     */
    public function setAdyenPaymentOrderId(int $id): CreditmemoInterface;

    /**
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): CreditmemoInterface;

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime;

    /**
     * @param \DateTime $createdAt
     * @return $this
     */
    public function setCreatedAt(\DateTime $createdAt): CreditmemoInterface;

    /**
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime;

    /**
     * @param \DateTime $updatedAt
     * @return $this
     */
    public function setUpdatedAt(\DateTime $updatedAt): CreditmemoInterface;
}

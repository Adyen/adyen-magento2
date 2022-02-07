<?php


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
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const STATUS = 'status';

    /**
     * Gets the ID for the creditmemo.
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
     * Gets the Pspreference for the creditmemo(capture).
     *
     * @return int|null Pspreference.
     */
    public function getPspreference();

    /**
     * Sets Pspreference.
     *
     * @param string $pspreference
     * @return $this
     */
    public function setPspreference($pspreference);

    /**
     * Get original reference
     */
    public function getOriginalReference();

    /**
     * Set original reference
     */
    public function setOriginalReference($originalReference);

    /**
     * Gets the CreditmemoID for the creditmemo.
     *
     * @return int|null Creditmemo ID.
     */
    public function getCreditmemoId();

    /**
     * Sets CreditmemoID.
     *
     * @param int $creditmemoId
     * @return $this
     */
    public function setCreditmemoId($creditmemoId);

    /**
     * @return int|null
     */
    public function getAmount();

    /**
     * @param $amount
     */
    public function setAmount($amount);

    /**
     * @return int|null
     */
    public function getAdyenPaymentOrderId();

    /**
     * @param $id
     * @return mixed
     */
    public function setAdyenPaymentOrderId($id);

    /**
     * @return string|null
     */
    public function getStatus();

    /**
     * @param $status
     */
    public function setStatus($status);

    /**
     * @return mixed
     */
    public function getCreatedAt();

    /**
     * @param $createdAt
     */
    public function setCreatedAt($createdAt);

    /**
     * @return mixed
     */
    public function getUpdatedAt();

    /**
     * @param $updatedAt
     */
    public function setUpdatedAt($updatedAt);
}

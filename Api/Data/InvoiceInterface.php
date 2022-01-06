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
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Data;

interface InvoiceInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    const ENTITY_ID = 'entity_id';
    const PSPREFERENCE = 'pspreference';

    /** @deprecated */
    const ORIGINAL_REFERENCE = 'original_reference';

    const ACQUIRER_REFERENCE = 'acquirer_reference';
    const INVOICE_ID = 'invoice_id';
    const ADYEN_ORDER_PAYMENT_ID = 'adyen_order_payment_id';
    const AMOUNT = 'amount';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const STATUS = 'status';

    const STATUS_PENDING_WEBHOOK = 'Pending Webhook';
    const STATUS_SUCCESSFUL = 'Successful';
    const STATUS_FAILED = 'Failed';

    /**
     * Gets the ID for the invoice.
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
     * Gets the Pspreference for the invoice(capture).
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
     * @return mixed
     */
    public function getOriginalReference();

    /**
     * @param  $originalReference
     * @return mixed
     */
    public function setOriginalReference($originalReference);

    /**
     * Gets the AcquirerReference for the invoice.
     *
     * @return int|null Acquirerreference.
     */
    public function getAcquirerReference();

    /**
     * Sets AcquirerReference.
     *
     * @param string $acquirerReference
     * @return $this
     */
    public function setAcquirerReference($acquirerReference);

    /**
     * Gets the InvoiceID for the invoice.
     *
     * @return int|null Invoice ID.
     */
    public function getInvoiceId();

    /**
     * Sets InvoiceID.
     *
     * @param int $invoiceId
     * @return $this
     */
    public function setInvoiceId($invoiceId);

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

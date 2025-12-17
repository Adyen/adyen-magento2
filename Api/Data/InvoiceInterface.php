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

use DateTime;

interface InvoiceInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    const ENTITY_ID = 'entity_id';
    const PSPREFERENCE = 'pspreference';
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

    public function getEntityId();

    public function setEntityId($entityId);

    /**
     * @return string|null
     */
    public function getPspreference(): ?string;

    /**
     * @param string $pspReference
     * @return InvoiceInterface
     */
    public function setPspreference(string $pspReference): InvoiceInterface;

    /**
     * @return string|null
     */
    public function getAcquirerReference(): ?string;

    /**
     * @param string $acquirerReference
     * @return InvoiceInterface
     */
    public function setAcquirerReference(string $acquirerReference): InvoiceInterface;

    /**
     * @return int|null
     */
    public function getInvoiceId(): ?int;

    /**
     * @param int $invoiceId
     * @return InvoiceInterface
     */
    public function setInvoiceId(int $invoiceId): InvoiceInterface;

    /**
     * @return float|null
     */
    public function getAmount(): ?float;

    /**
     * @param float $amount
     * @return InvoiceInterface
     */
    public function setAmount(float $amount): InvoiceInterface;

    /**
     * @return int|null
     */
    public function getAdyenPaymentOrderId(): ?int;

    /**
     * @param int $id
     * @return InvoiceInterface
     */
    public function setAdyenPaymentOrderId(int $id): InvoiceInterface;

    /**
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * @param string $status
     * @return InvoiceInterface
     */
    public function setStatus(string $status): InvoiceInterface;

    /**
     * @return DateTime|null
     */
    public function getCreatedAt(): ?DateTime;

    /**
     * @param DateTime $createdAt
     * @return InvoiceInterface
     */
    public function setCreatedAt(DateTime $createdAt): InvoiceInterface;

    /**
     * @return DateTime|null
     */
    public function getUpdatedAt(): ?DateTime;

    /**
     * @param DateTime $updatedAt
     * @return InvoiceInterface
     */
    public function setUpdatedAt(DateTime $updatedAt): InvoiceInterface;
}

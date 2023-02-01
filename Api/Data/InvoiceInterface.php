<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen B.V.
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

    public function getEntityId(): ?int;

    public function setEntityId(int $entityId): InvoiceInterface;

    public function getPspreference(): ?string;

    public function setPspreference(string $pspReference): InvoiceInterface;

    public function getAcquirerReference(): ?string;

    public function setAcquirerReference(string $acquirerReference): InvoiceInterface;

    public function getInvoiceId(): ?int;

    public function setInvoiceId(int $invoiceId): InvoiceInterface;

    public function getAmount(): ?int;

    public function setAmount(int $amount): InvoiceInterface;

    public function getAdyenPaymentOrderId(): ?int;

    public function setAdyenPaymentOrderId(int $id): InvoiceInterface;

    public function getStatus(): ?string;

    public function setStatus(string $status): InvoiceInterface;

    public function getCreatedAt(): ?DateTime;

    public function setCreatedAt(DateTime $createdAt): InvoiceInterface;

    public function getUpdatedAt(): ?DateTime;

    public function setUpdatedAt(DateTime $updatedAt): InvoiceInterface;
}

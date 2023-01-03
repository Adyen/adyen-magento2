<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Data;

use DateTime;

interface NotificationInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */


    const ENTITY_ID = 'entity_id';
    const PSPREFRENCE = 'pspreference';
    const ORIGINAL_REFERENCE = 'original_reference';
    const MERCHANT_REFERENCE = 'merchant_reference';
    const EVENT_CODE = 'event_code';
    const SUCCESS = 'success';
    const PAYMENT_METHOD = 'payment_method';
    const AMOUNT_VALUE = 'amount_value';
    const AMOUNT_CURRENCY = 'amount_currency';
    const REASON = 'reason';
    const LIVE = 'live';
    const DONE = 'done';
    const ADDITIONAL_DATA = 'additional_data';
    const PROCESSING = 'processing';
    const ERROR_COUNT = 'error_count';
    const ERROR_MESSAGE = 'error_message';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Cannot use PHP typing due to Magento constraints
     *
     * @return int|null Entity ID.
     */
    public function getEntityId();

    /**
     * Cannot use PHP typing due to Magento constraints
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    public function getPspreference(): ?string;

    public function setPspreference(string $pspreference): NotificationInterface;

    public function getOriginalReference(): ?string;

    public function setOriginalReference(string $originalReference): NotificationInterface;

    public function getMerchantReference(): ?string;

    public function setMerchantReference(string $merchantReference): NotificationInterface;

    public function getEventCode(): ?string;

    public function setEventCode(string $eventCode): NotificationInterface;

    public function getSuccess(): ?string;

    public function setSuccess(string $success): NotificationInterface;

    public function getPaymentMethod(): ?string;

    public function setPaymentMethod(string $paymentMethod): NotificationInterface;

    public function getAmountValue(): ?int;

    public function setAmountValue(int $amountValue): NotificationInterface;

    public function getAmountCurrency(): ?string;

    public function setAmountCurrency(string $amountCurrency): NotificationInterface;

    public function getReason(): ?string;

    public function setReason(string $reason): NotificationInterface;

    public function getLive(): ?string;

    public function setLive(string $live): NotificationInterface;

    public function getAdditionalData(): ?string;

    public function setAdditionalData(string $additionalData): NotificationInterface;

    public function getDone(): ?bool;

    public function setDone(bool $done): NotificationInterface;

    public function getProcessing(): ?bool;

    public function setProcessing(bool $processing): NotificationInterface;

    public function getErrorCount(): ?int;

    public function setErrorCount(int $errorCount): NotificationInterface;

    public function getErrorMessage(): ?string;

    public function setErrorMessage(string $errorMessage): NotificationInterface;

    public function getCreatedAt(): ?DateTime;

    public function setCreatedAt(DateTime $createdAt): NotificationInterface;

    public function getUpdatedAt(): ?DateTime;

    public function setUpdatedAt(DateTime $timestamp): NotificationInterface;
}

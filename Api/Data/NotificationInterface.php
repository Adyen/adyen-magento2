<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Data;

interface NotificationInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    const TABLE_NAME = 'adyen_notification';
    const TABLE_NAME_ALIAS = 'notification';
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
     * @return mixed
     */
    public function getEntityId();

    /**
     * @param $entityId
     * @return mixed
     */
    public function setEntityId($entityId);

    /**
     * @return string|null
     */
    public function getPspreference(): ?string;

    /**
     * @param string $pspreference
     * @return NotificationInterface
     */
    public function setPspreference(string $pspreference): NotificationInterface;

    /**
     * @return string|null
     */
    public function getOriginalReference(): ?string;

    /**
     * @param string|null $originalReference
     * @return NotificationInterface
     */
    public function setOriginalReference(?string $originalReference): NotificationInterface;

    /**
     * @return string|null
     */
    public function getMerchantReference(): ?string;

    /**
     * @param string $merchantReference
     * @return NotificationInterface
     */
    public function setMerchantReference(string $merchantReference): NotificationInterface;

    /**
     * @return string|null
     */
    public function getEventCode(): ?string;

    /**
     * @param string $eventCode
     * @return NotificationInterface
     */
    public function setEventCode(string $eventCode): NotificationInterface;

    /**
     * @return string|null
     */
    public function getSuccess(): ?string;

    /**
     * @param string $success
     * @return NotificationInterface
     */
    public function setSuccess(string $success): NotificationInterface;

    /**
     * @return string|null
     */
    public function getPaymentMethod(): ?string;

    /**
     * @param string $paymentMethod
     * @return NotificationInterface
     */
    public function setPaymentMethod(string $paymentMethod): NotificationInterface;

    /**
     * @return int|null
     */
    public function getAmountValue(): ?int;

    /**
     * @param int $amountValue
     * @return NotificationInterface
     */
    public function setAmountValue(int $amountValue): NotificationInterface;

    /**
     * @return string|null
     */
    public function getAmountCurrency(): ?string;

    /**
     * @param string $amountCurrency
     * @return NotificationInterface
     */
    public function setAmountCurrency(string $amountCurrency): NotificationInterface;

    /**
     * @return string|null
     */
    public function getReason(): ?string;

    /**
     * @param string $reason
     * @return NotificationInterface
     */
    public function setReason(string $reason): NotificationInterface;

    /**
     * @return string|null
     */
    public function getLive(): ?string;

    /**
     * @param string $live
     * @return NotificationInterface
     */
    public function setLive(string $live): NotificationInterface;

    /**
     * @return string|null
     */
    public function getAdditionalData(): ?string;

    /**
     * @param string $additionalData
     * @return NotificationInterface
     */
    public function setAdditionalData(string $additionalData): NotificationInterface;

    /**
     * @return bool|null
     */
    public function getDone(): ?bool;

    /**
     * @param bool $done
     * @return NotificationInterface
     */
    public function setDone(bool $done): NotificationInterface;

    /**
     * @return bool|null
     */
    public function getProcessing(): ?bool;

    /**
     * @param bool $processing
     * @return NotificationInterface
     */
    public function setProcessing(bool $processing): NotificationInterface;

    /**
     * @return int|null
     */
    public function getErrorCount(): ?int;

    /**
     * @param int $errorCount
     * @return NotificationInterface
     */
    public function setErrorCount(int $errorCount): NotificationInterface;

    /**
     * @return string|null
     */
    public function getErrorMessage(): ?string;

    /**
     * @param string $errorMessage
     * @return NotificationInterface
     */
    public function setErrorMessage(string $errorMessage): NotificationInterface;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $createdAt
     * @return NotificationInterface
     */
    public function setCreatedAt(string $createdAt): NotificationInterface;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * @param string $timestamp
     * @return NotificationInterface
     */
    public function setUpdatedAt(string $timestamp): NotificationInterface;
}

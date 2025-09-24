<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Data;

use DateTime;

interface AnalyticsEventInterface
{
    const ADYEN_ANALYTICS_EVENT = 'adyen_analytics_event';
    const ENTITY_ID = 'entity_id';
    const UUID = 'uuid';
    const RELATION_ID = 'relation_id';
    const TYPE = 'type';
    const TOPIC = 'topic';
    const MESSAGE = 'message';
    const ERROR_TYPE = 'error_type';
    const ERROR_CODE = 'error_code';
    const ERROR_COUNT = 'error_count';
    const STATUS = 'status';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const SCHEDULED_PROCESSING_TIME = 'scheduled_processing_time';
    const MAX_ERROR_COUNT = 5;

    public function getEntityId();

    public function setEntityId($entityId);

    public function getRelationId(): string;

    public function setRelationId(string $relationId): AnalyticsEventInterface;

    public function getUuid(): string;

    public function setUuid(string $uuid): AnalyticsEventInterface;

    public function getType(): string;

    public function setType(string $type): AnalyticsEventInterface;

    public function getTopic(): string;

    public function setTopic(string $topic): AnalyticsEventInterface;

    public function getMessage(): ?string;

    public function setMessage(?string $message = null): AnalyticsEventInterface;

    public function getErrorType(): ?string;

    public function setErrorType(?string $errorType = null): AnalyticsEventInterface;

    public function getErrorCode(): ?string;

    public function setErrorCode(?string $errorCode = null): AnalyticsEventInterface;

    public function getErrorCount(): int;

    public function setErrorCount(int $errorCount): AnalyticsEventInterface;

    public function getStatus(): string;

    public function setStatus(string $status): AnalyticsEventInterface;

    public function getCreatedAt(): string;

    public function setCreatedAt(string $createdAt): AnalyticsEventInterface;

    public function getCreatedAtTimestamp(): int;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt = null): AnalyticsEventInterface;

    public function getScheduledProcessingTime(): ?string;

    public function setScheduledProcessingTime(?string $scheduledProcessingTime = null): AnalyticsEventInterface;
}

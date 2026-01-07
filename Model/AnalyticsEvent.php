<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use DateTime;
use Magento\Framework\Model\AbstractModel;

class AnalyticsEvent extends AbstractModel implements AnalyticsEventInterface
{
    protected function _construct()
    {
        $this->_init('Adyen\Payment\Model\ResourceModel\AnalyticsEvent');
    }

    public function getUuid(): string
    {
        return $this->getData(self::UUID);
    }

    public function setUuid(string $uuid): AnalyticsEventInterface
    {
        return $this->setData(self::UUID, $uuid);
    }

    public function getRelationId(): string
    {
        return $this->getData(self::RELATION_ID);
    }

    public function setRelationId(string $relationId): AnalyticsEventInterface
    {
        return $this->setData(self::RELATION_ID, $relationId);
    }

    public function getType(): string
    {
        return $this->getData(self::TYPE);
    }

    public function setType(string $type): AnalyticsEventInterface
    {
        return $this->setData(self::TYPE, $type);
    }

    public function getTopic(): string
    {
        return $this->getData(self::TOPIC);
    }

    public function setTopic(string $topic): AnalyticsEventInterface
    {
        return $this->setData(self::TOPIC, $topic);
    }

    public function getMessage(): ?string
    {
        return $this->getData(self::MESSAGE);
    }

    public function setMessage(?string $message = null): AnalyticsEventInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    public function getVersion(): string
    {
        return $this->getData(self::VERSION);
    }

    public function setVersion(string $version): AnalyticsEventInterface
    {
        return $this->setData(self::VERSION, $version);
    }

    public function getErrorType(): ?string
    {
        return $this->getData(self::ERROR_TYPE);
    }

    /**
     * This field refers to exception type related to the unexpected exception in case of `error` logging
     *
     * @param string|null $errorType
     * @return AnalyticsEventInterface
     */
    public function setErrorType(?string $errorType = null): AnalyticsEventInterface
    {
        return $this->setData(self::ERROR_TYPE, $errorType);
    }

    public function getErrorCode(): ?string
    {
        return $this->getData(self::ERROR_CODE);
    }

    /**
     * This field refers to code related to the unexpected exception in case of `error` logging
     *
     * @param string|null $errorCode
     * @return AnalyticsEventInterface
     */
    public function setErrorCode(?string $errorCode = null): AnalyticsEventInterface
    {
        return $this->setData(self::ERROR_CODE, $errorCode);
    }

    public function getErrorCount(): int
    {
        return $this->getData(self::ERROR_COUNT);
    }

    public function setErrorCount(int $errorCount): AnalyticsEventInterface
    {
        return $this->setData(self::ERROR_COUNT, $errorCount);
    }

    public function getStatus(): string
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus(string $status): AnalyticsEventInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getCreatedAt(): string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): AnalyticsEventInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getCreatedAtTimestamp(): int
    {
        $dateTime = new DateTime($this->getCreatedAt());
        return $dateTime->getTimestamp();
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(?string $updatedAt = null): AnalyticsEventInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    public function getScheduledProcessingTime(): ?string
    {
        return $this->getData(self::SCHEDULED_PROCESSING_TIME);
    }

    public function setScheduledProcessingTime(?string $scheduledProcessingTime = null): AnalyticsEventInterface
    {
        return $this->setData(self::SCHEDULED_PROCESSING_TIME, $scheduledProcessingTime);
    }
}

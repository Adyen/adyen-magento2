<?php
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
        return $this->getData(self::RELATION_ID);
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

    public function getErrorCount(): int
    {
        return $this->getData(self::ERROR_COUNT);
    }

    public function setErrorCount(int $errorCount): AnalyticsEventInterface
    {
        return $this->setData(self::ERROR_COUNT, $errorCount);
    }

    public function getStatus(): int
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus(int $status): AnalyticsEventInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getCreatedAt(): DateTime
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(DateTime $createdAt): AnalyticsEventInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(?DateTime $updatedAt = null): AnalyticsEventInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}

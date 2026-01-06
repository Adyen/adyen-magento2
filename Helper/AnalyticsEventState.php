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

namespace Adyen\Payment\Helper;

use Adyen\Payment\Helper\Util\Uuid;

class AnalyticsEventState
{
    const EVENT_NAME = 'adyen_analytics_event';
    /**
     * Temporary storage of relation_id
     */
    private ?string $relationId = null;

    /**
     * Temporary storage of topic
     */
    private ?string $topic = null;

    /**
     * @return string|null
     */
    public function getRelationId(): ?string
    {
        if (!isset($this->relationId)) {
            $this->relationId = Uuid::generateV4();
        }

        return $this->relationId;
    }

    public function setTopic(?string $topic): void
    {
        $this->topic = $topic;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }
}

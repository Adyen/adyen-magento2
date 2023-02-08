<?php

namespace Adyen\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class NotificationProcessor implements OptionSourceInterface
{
    public const CRON = 'cron';
    public const QUEUE = 'queue';

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::CRON, 'label' => __('Cron')],
            ['value' => self::QUEUE, 'label' => __('Queue')],
        ];
    }
}

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

namespace Adyen\Payment\Observer;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class DispatchAnalyticsEvent implements ObserverInterface
{
    public function __construct(
        protected readonly ManagerInterface $eventManager
    ) { }

    public function execute(Observer $observer)
    {
        // Sample data for dispatching the event
        $eventData = [
            'relationId' => '12345',
            'type' => 'expectedStart',
            'topic' => 'payment-methods-manager-interface',
            'message' => 'Sample payment analytics message.'
        ];

        // Dispatch the event
        $this->eventManager->dispatch('payment_method_adyen_analytics', ['data' => $eventData]);
    }
}

<?php
namespace Adyen\Payment\Observer;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Adyen\Payment\Api\AdyenAnalyticsRepositoryInterface;

class DispatchAnalyticsEvent implements ObserverInterface
{
    protected ManagerInterface $eventManager;
    protected AdyenAnalyticsRepositoryInterface $adyenAnalyticsRepository;

    public function __construct(
        ManagerInterface $eventManager,
        AdyenAnalyticsRepositoryInterface $adyenAnalyticsRepository
    ) {
        $this->eventManager = $eventManager;
        $this->adyenAnalyticsRepository = $adyenAnalyticsRepository;
    }

    public function execute(Observer $observer)
    {
        // Sample data for dispatching the event
        $eventData = [
            'checkoutAttemptId' => '12345',
            'eventType' => 'payment_attempt',
            'topic' => 'payment_method_adyen_analytics',
            'message' => 'Sample payment analytics message.',
            'errorCount' => 0,
            'done' => false,
        ];

        // Dispatch the event
        $this->eventManager->dispatch('payment_method_adyen_analytics', ['data' => $eventData]);
    }
}

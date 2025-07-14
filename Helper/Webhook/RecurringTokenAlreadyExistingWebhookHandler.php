<?php

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Adyen\Payment\Model\Notification;
class RecurringTokenAlreadyExistingWebhookHandler implements WebhookHandlerInterface
{
    private OrderRepositoryInterface $orderRepository;
    private AdyenLogger $adyenLogger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        AdyenLogger $adyenLogger
    ) {
        $this->orderRepository = $orderRepository;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * Handle the recurring.token.alreadyExisting webhook.
     *
     * @param Order $order
     * @param Notification $notification
     * @param string $transitionState
     * @return Order
     */
    public function handleWebhook(
        Order $order,
        Notification $notification,
        string $transitionState
    ): Order {
        try {
            $order->addCommentToStatusHistory(
                __('Recurring token already existed and was linked to this customer successfully.')
            );

            $this->adyenLogger->info(sprintf(
                'Handled recurring.token.alreadyExisting webhook for order %s (pspReference: %s)',
                $order->getIncrementId(),
                $notification->getPspreference()
            ));
        } catch (\Exception $e) {
            $this->adyenLogger->error(sprintf(
                'Error handling recurring.token.alreadyExisting webhook for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }

        return $order;
    }
}

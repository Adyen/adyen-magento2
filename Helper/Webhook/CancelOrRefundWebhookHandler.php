<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order as MagentoOrder;

class CancelOrRefundWebhookHandler implements WebhookHandlerInterface
{
    const MODIFICATION_ACTION = 'modification.action';

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var SerializerInterface */
    private $serializer;

    /** @var Order */
    private $orderHelper;

    public function __construct(
        AdyenLogger $adyenLogger,
        SerializerInterface $serializer,
        Order $orderHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->serializer = $serializer;
        $this->orderHelper = $orderHelper;
    }

    /**
     * @throws LocalizedException
     * @throws \Exception
     */
    public function handleWebhook(MagentoOrder $order, Notification $notification, string $transitionState): MagentoOrder
    {
        $modificationAction = $this->getModificationAction($notification);
        $orderId = $order->getIncrementId();

        if (isset($modificationAction)) {
            if ($modificationAction === 'cancel') {
                $order = $this->orderHelper->holdCancelOrder($order, true);
            } elseif ($modificationAction === 'refund') {
                $order = $this->orderHelper->refundOrder($order, $notification);
            }
        } else {
            if ($order->isCanceled() || $order->getState() === MagentoOrder::STATE_HOLDED) {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    sprintf('Order %s is already cancelled or held, so do nothing', $order->getIncrementId())
                );
            } else {
                if ($order->canCancel() || $order->canHold()) {
                    $this->adyenLogger->addAdyenNotificationCronjob(sprintf('Attempting to cancel order %s', $orderId));
                    $this->orderHelper->holdCancelOrder($order, true);
                } else {
                    $this->adyenLogger->addAdyenNotificationCronjob(sprintf('Attempting to refund order %s', $orderId));
                    $this->orderHelper->refundOrder($order, $notification);
                }
            }
        }

        return $order;
    }

    private function getModificationAction(Notification $notification): ?string
    {
        $modificationAction = null;
        $additionalData = $notification->getAdditionalData();
        $additionalData = !empty($additionalData) ? $this->serializer->unserialize($additionalData) : [];

        if (array_key_exists(self::MODIFICATION_ACTION, $additionalData)) {
            $modificationAction = $additionalData[self::MODIFICATION_ACTION];
        }

        return $modificationAction;
    }
}

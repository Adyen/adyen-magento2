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

use Adyen\Payment\Api\CleanupAdditionalInformationInterface;
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

    /**
     * @param AdyenLogger $adyenLogger
     * @param SerializerInterface $serializer
     * @param Order $orderHelper
     * @param CleanupAdditionalInformationInterface $cleanupAdditionalInformation
     */
    public function __construct(
        private readonly AdyenLogger $adyenLogger,
        private readonly SerializerInterface $serializer,
        private readonly Order $orderHelper,
        private readonly CleanupAdditionalInformationInterface $cleanupAdditionalInformation
    ) { }

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
                $this->adyenLogger->addAdyenNotification(
                    sprintf(
                        'Order %s is already cancelled or held, so do nothing', $order->getIncrementId()
                    ),
                    [
                        'pspReference' => $notification->getPspreference(),
                        'merchantReference' => $notification->getMerchantReference()
                    ]
                );
            } else {
                if ($order->canCancel() || $order->canHold()) {
                    $this->adyenLogger->addAdyenNotification(
                        sprintf('Attempting to cancel order %s', $orderId),
                        [
                            'pspReference' => $notification->getPspreference(),
                            'merchantReference' => $notification->getMerchantReference()
                        ]
                    );
                    $this->orderHelper->holdCancelOrder($order, $notification);
                } else {
                    $this->adyenLogger->addAdyenNotification(
                        sprintf('Attempting to refund order %s', $orderId),
                        [
                            'pspReference' => $notification->getPspreference(),
                            'merchantReference' => $notification->getMerchantReference()
                        ]
                    );
                    $this->orderHelper->refundOrder($order, $notification);
                }
            }
        }

        // Clean-up the data temporarily stored in `additional_information`
        $this->cleanupAdditionalInformation->execute($order->getPayment());

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

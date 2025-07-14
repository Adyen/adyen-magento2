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

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;

class RecurringTokenDisabledWebhookHandler implements WebhookHandlerInterface
{
    /**
     * @param AdyenLogger $adyenLogger
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     */
    public function __construct(
        private readonly AdyenLogger $adyenLogger,
        private readonly PaymentTokenManagement $paymentTokenManagement,
        private readonly PaymentTokenRepositoryInterface $paymentTokenRepository
    ) { }

    public function handleWebhook(Order $order, Notification $notification, string $transitionState): Order
    {
        $vaultToken = $this->paymentTokenManagement->getByGatewayToken(
            $notification->getPspreference(),
            $order->getPayment()->getMethodInstance()->getCode(),
            $order->getCustomerId()
        );

        if (isset($vaultToken)) {
            $vaultToken->setIsActive(false);
            $vaultToken->setIsVisible(false);

            $this->paymentTokenRepository->save($vaultToken);

            $this->adyenLogger->addAdyenNotification(
                sprintf(
                    "Vault payment token with entity_id: %s disabled due to the failing %s webhook notification.",
                    $vaultToken->getEntityId(),
                    $notification->getEventCode()
                ),
                [
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );
        }

        return $order;
    }
}

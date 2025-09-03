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

use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Adyen\Payment\Model\Notification;

class RecurringTokenCreatedWebhookHandler implements WebhookHandlerInterface
{
    public function __construct(
        private readonly AdyenLogger $adyenLogger,
        private readonly Vault $vaultHelper
    ) { }

    /**
     * Handle the recurring.token.created webhook.
     *
     * @param Order $order
     * @param Notification $notification
     * @param string $transitionState
     * @return Order
     * @throws LocalizedException
     */
    public function handleWebhook(Order $order, Notification $notification, string $transitionState): Order
    {
        $paymentMethodInstance = $order->getPayment()->getMethodInstance();
        $paymentMethodCode = $paymentMethodInstance->getCode();
        $storeId = $paymentMethodInstance->getStore();
        $isRecurringEnabled = $this->vaultHelper->getPaymentMethodRecurringActive($paymentMethodCode, $storeId);

        if ($isRecurringEnabled) {
            $paymentToken = $this->vaultHelper->createVaultToken(
                $order->getPayment(),
                $notification->getPspreference()
            );
            $extensionAttributes = $this->vaultHelper->getExtensionAttributes($order->getPayment());
            $extensionAttributes->setVaultPaymentToken($paymentToken);
        } else {
            $this->adyenLogger->addAdyenNotification(
                'The vault token has not been stored as the recurring configuration is disabled.',
                [
                    'pspReference' => $notification->getPspreference(),
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );
        }

        return $order;
    }
}

<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;

class RecurringContractWebhookHandler implements WebhookHandlerInterface
{
    /**
     * @var AdyenLogger
     */
    private AdyenLogger $adyenLogger;

    /**
     * @var PaymentTokenManagement
     */
    private PaymentTokenManagement $paymentTokenManagement;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private PaymentTokenRepositoryInterface $paymentTokenRepository;

    /**
     * @param AdyenLogger $adyenLogger
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     */
    public function __construct(
        AdyenLogger $adyenLogger,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenRepositoryInterface $paymentTokenRepository
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenRepository = $paymentTokenRepository;
    }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @param string $transitionState
     * @return MagentoOrder
     * @throws LocalizedException
     */
    public function handleWebhook(
        MagentoOrder $order,
        Notification $notification,
        string $transitionState
    ): MagentoOrder {
        if (!$notification->isSuccessful()) {
            $this->handleFailedNotification($order, $notification);
        }

        return $order;
    }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @return void
     * @throws LocalizedException
     */
    private function handleFailedNotification(MagentoOrder $order, Notification $notification): void
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
                    $notification->getEventCode()
                ),
                [
                    'merchantReference' => $notification->getMerchantReference()
                ]
            );
        }
    }
}

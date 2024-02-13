<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenPosCloudInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Adyen\Payment\Model\Sales\OrderRepository;

class AdyenPosCloud implements AdyenPosCloudInterface
{
    private CommandPoolInterface $commandPool;
    private Json $jsonSerializer;
    protected AdyenLogger $adyenLogger;
    protected OrderRepository $orderRepository;

    public function __construct(
        CommandPoolInterface     $commandPool,
        OrderRepository $orderRepository,
        Json                     $jsonSerializer,
        AdyenLogger              $adyenLogger
    )
    {
        $this->commandPool = $commandPool;
        $this->orderRepository = $orderRepository;
        $this->jsonSerializer = $jsonSerializer;
        $this->adyenLogger = $adyenLogger;
    }

    public function pay(string $payload): void
    {
        $payload = $this->jsonSerializer->unserialize($payload);
        $orderId = $payload['orderId'];
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $exception) {
            $errorMessage = sprintf("Order for ID %s not found!", $orderId);
            $this->adyenLogger->error($errorMessage);

            throw $exception;
        }
        $paymentDataObject = new PaymentDataObject($order, $order->getPayment());

        try {
            $posCommand = $this->commandPool->get('authorize');
            $posCommand->execute(['payment' => $paymentDataObject]);
        } catch (NoSuchEntityException $exception) {
            $errorMessage = sprintf("Order for ID %s not found!", $orderId);
            $this->adyenLogger->error($errorMessage);

            throw $exception;
        }
    }
}

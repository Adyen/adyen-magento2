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

use Adyen\Payment\Api\AdyenPOSCloudInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class AdyenPOSCloud implements AdyenPOSCloudInterface
{
    private CommandPoolInterface $commandPool;
    private Json $jsonSerializer;
    protected AdyenLogger $adyenLogger;
    protected OrderRepositoryInterface $orderRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CommandPoolInterface     $commandPool,
        Json                     $jsonSerializer,
        AdyenLogger              $adyenLogger
    )
    {
        $this->commandPool = $commandPool;
        $this->jsonSerializer = $jsonSerializer;
        $this->adyenLogger = $adyenLogger;
    }

    public function pay(string $payload): void
    {
        $payload = $this->jsonSerializer->unserialize($payload);
        $orderId = $payload['oderId'];

        try {
            $posCommand = $this->commandPool->get('authorize');
            $posCommand->execute(['orderId' => $orderId]);
        } catch (NoSuchEntityException $exception) {
            $errorMessage = sprintf("Order for ID %s not found!", $orderId);
            $this->adyenLogger->error($errorMessage);

            throw $exception;
        }
    }
}

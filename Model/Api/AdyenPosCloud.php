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
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;

class AdyenPosCloud implements AdyenPosCloudInterface
{
    private CommandPoolInterface $commandPool;
    private Json $jsonSerializer;
    protected AdyenLogger $adyenLogger;
    protected OrderRepository $orderRepository;
    protected PaymentDataObjectFactoryInterface $paymentDataObjectFactory;

    public function __construct(
        CommandPoolInterface $commandPool,
        OrderRepository      $orderRepository,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        Json                 $jsonSerializer,
        AdyenLogger          $adyenLogger
    )
    {
        $this->commandPool = $commandPool;
        $this->orderRepository = $orderRepository;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->adyenLogger = $adyenLogger;
    }

    public function pay(int $orderId): void
    {
        $order = $this->orderRepository->get($orderId);
        $this->execute($order);
    }

    protected function execute(OrderInterface $order): void
    {
        $payment = $order->getPayment();
        $paymentDataObject = $this->paymentDataObjectFactory->create($payment);
        $posCommand = $this->commandPool->get('authorize');
        $posCommand->execute(['payment' => $paymentDataObject]);
    }
}

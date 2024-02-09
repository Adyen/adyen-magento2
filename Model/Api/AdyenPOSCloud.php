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
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;

class AdyenPOSCloud implements AdyenPOSCloudInterface
{
    private CommandPoolInterface $commandPool;

    public function __construct(
        CommandPoolInterface $commandPool
    ) {
        $this->commandPool = $commandPool;
    }

    public function pay(int $orderId, string $payload): void
    {
        $donationsCaptureCommand = $this->commandPool->get('authorize');
        $donationsCaptureCommand->execute(['payment' => $payload]);
    }
}

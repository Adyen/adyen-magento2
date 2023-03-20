<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

use Magento\Sales\Model\Config\Source\Order\Status;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config;

/**
 * Order Statuses source model
 */
class PaymentConfirmed extends Status
{
    /**
     * @var string[]
     */
    private array $stateStatuses = [
        Order::STATE_PROCESSING,
    ];

    /**
     * @param Config $orderConfig
     */
    public function __construct(Config $orderConfig)
    {
        $this->_orderConfig = $orderConfig;
    }

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        $statuses = $this->stateStatuses
            ? $this->_orderConfig->getStateStatuses($this->stateStatuses)
            : $this->_orderConfig->getStatuses();

        $options = [];
        foreach ($statuses as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }

        return $options;
    }
}

<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Adyen\Payment\Model\Config\Source\Status;

use Adyen\Payment\Helper\Webhook;

/**
 * Order Statuses source model
 */
class ProcessingMaintain extends \Magento\Sales\Model\Config\Source\Order\Status
{
    /**
     * @var string[]
     */
    private $stateStatuses = [
        \Magento\Sales\Model\Order::STATE_PROCESSING,
    ];

    private $adyenStateStatuses = AdyenStates::STATE_MAINTAIN_STATUS;

    /**
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     */
    public function __construct(\Magento\Sales\Model\Order\Config $orderConfig)
    {
        $this->_orderConfig = $orderConfig;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $statuses = $this->stateStatuses
            ? $this->_orderConfig->getStateStatuses($this->stateStatuses)
            : $this->_orderConfig->getStatuses();

        $statuses = array_merge($statuses, $this->adyenStateStatuses);

        $options = [];
        foreach ($statuses as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }

        return $options;
    }
}

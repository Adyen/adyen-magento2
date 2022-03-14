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
    protected $_stateStatuses = [
        \Magento\Sales\Model\Order::STATE_PROCESSING,
    ];

    protected $_adyenStateStatuses = Webhook::STATE_MAINTAIN;

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
        $statuses = $this->_stateStatuses
            ? $this->_orderConfig->getStateStatuses($this->_stateStatuses)
            : $this->_orderConfig->getStatuses();

        $statuses = array_merge($statuses, $this->_adyenStateStatuses);

        $options = [];
        foreach ($statuses as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options;
    }
}

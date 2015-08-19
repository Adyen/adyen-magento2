<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Order Statuses source model
 */
namespace Adyen\Payment\Model\Config\Source;

class RecurringType implements \Magento\Framework\Option\ArrayInterface
{
    const UNDEFINED_OPTION_LABEL = 'NONE';

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;


    protected $_adyenHelper;


    /**
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     */
    public function __construct(
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Adyen\Payment\Helper\Data $adyenHelper
    )
    {
        $this->_orderConfig = $orderConfig;
        $this->_adyenHelper = $adyenHelper;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $recurringTypes = $this->_adyenHelper->getRecurringTypes();

        $options = [['value' => '', 'label' => __(self::UNDEFINED_OPTION_LABEL)]];
        foreach ($recurringTypes as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options;
    }
}

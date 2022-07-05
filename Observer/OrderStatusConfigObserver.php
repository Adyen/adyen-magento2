<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\Config;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderStatusConfigObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var WriterInterface
     */
    private $configWriter;


    const PAYMENT_METHODS = [
        Config::XML_ADYEN_CC,
        Config::XML_ADYEN_HPP,
        Config::XML_ADYEN_ONECLICK,
        Config::XML_ADYEN_BOLETO,
        Config::XML_ADYEN_PAY_BY_LINK
    ];


    /**
     * OrderStatusConfigObserver constructor.
     * @param Config $configHelper
     * @param WriterInterface $configWriter
     */
    public function __construct(
        Config $configHelper,
        WriterInterface $configWriter
    ) {
        $this->configHelper = $configHelper;
        $this->configWriter = $configWriter;
    }

    /**
     * Execute when there is a change in the payment section in the admin backend (adyen config is in this section)
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $storeId = $observer->getData('store');

        if (empty($storeId)) {
            $storeId = null;
        }

        $orderStatusActiveConfig = $this->configHelper->getConfigData('order_status', Config::XML_ADYEN_ABSTRACT_PREFIX, $storeId);

        foreach (self::PAYMENT_METHODS as $payment_method) {
            $this->configWriter->save(
                'payment/' . $payment_method . '/order_status',
                $orderStatusActiveConfig,
            );
        }
    }
}

<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Logger;

use Adyen\Payment\Helper\Config;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Store\Model\StoreManagerInterface;
use Monolog\Logger;

class AdyenLogger extends Logger
{
    /**
     * Detailed debug information
     */
    const ADYEN_DEBUG = 101;
    const ADYEN_NOTIFICATION = 201;
    const ADYEN_RESULT = 202;
    /**
     * Logging levels from syslog protocol defined in RFC 5424
     * Overrule the default to add Adyen specific loggers to log into seperate files
     *
     * @var array $levels Logging levels
     */
    protected static $levels = [
        100 => 'DEBUG',
        101 => 'ADYEN_DEBUG',
        200 => 'INFO',
        201 => 'ADYEN_NOTIFICATION',
        202 => 'ADYEN_RESULT',
        250 => 'NOTICE',
        300 => 'WARNING',
        400 => 'ERROR',
        500 => 'CRITICAL',
        550 => 'ALERT',
        600 => 'EMERGENCY',
    ];

    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(Config $config, StoreManagerInterface $storeManager, $name, array $handlers = array(), array $processors = array())
    {
        parent::__construct($name, $handlers, $processors);
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * Adds a webhook notification log record.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addAdyenNotification($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_NOTIFICATION, $message, $context);
    }

    public function addAdyenDebug($message, array $context = [])
    {
        $storeId = $this->storeManager->getStore()->getId();
        if ($this->config->debugLogsEnabled($storeId)) {
            return $this->addRecord(static::ADYEN_DEBUG, $message, $context);
        }
    }

    public function addAdyenWarning($message, array $context = []): bool
    {
        return $this->addRecord(static::WARNING, $message, $context);
    }

    public function addAdyenResult($message, array $context = [])
    {
        return $this->addRecord(static::ADYEN_RESULT, $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addAdyenInfoLog($message, array $context = [])
    {
        return $this->addRecord(static::INFO, $message, $context);
    }

    public function getOrderContext(MagentoOrder $order): array
    {
        return [
            'orderId' => $order->getId(),
            'orderIncrementId' => $order->getIncrementId(),
            'orderState' => $order->getState(),
            'orderStatus' => $order->getStatus()
        ];
    }

    public function getInvoiceContext(MagentoOrder\Invoice $invoice): array
    {
        try {
            $stateName = $invoice->getStateName();

            return [
                'invoiceId' => $invoice->getEntityId(),
                'invoiceIncrementId' => $invoice->getIncrementId(),
                'invoiceState' => $invoice->getState(),
                'invoiceStateName' => $stateName instanceof Phrase ? $stateName->getText() : $stateName,
                'invoiceWasPayCalled' => $invoice->wasPayCalled(),
                'invoiceCanCapture' => $invoice->canCapture(),
                'invoiceCanCancel' => $invoice->canCancel(),
                'invoiceCanVoid' => $invoice->canVoid(),
                'invoiceCanRefund' => $invoice->canRefund()
            ];
        } catch (\Throwable $e) {
            $this->addAdyenWarning('Failed to retrieve invoice context: ' . $e->getMessage());
            return [];
        }
    }
}

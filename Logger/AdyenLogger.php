<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Logger;

use Adyen\Payment\Helper\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Store\Model\StoreManagerInterface;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LoggerFactory;

class AdyenLogger
{
    const ADYEN_DEBUG = 101;
    const ADYEN_NOTIFICATION = 201;
    const ADYEN_RESULT = 202;
    const ADYEN_INFO = 203;
    const ADYEN_WARNING = 301;
    const ADYEN_ERROR = 401;

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param LoggerFactory $loggerFactory
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerFactory $loggerFactory,
        private array $handlers = [],
        private array $processors = []
    ) { }

    /**
     * Adds a webhook notification log record.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addAdyenNotification(string $message, array $context = []): bool
    {
        $logger = $this->generateLogger(self::ADYEN_NOTIFICATION);
        return $logger->addRecord(Level::Info, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     * @throws NoSuchEntityException
     */
    public function addAdyenDebug(string $message, array $context = []): bool
    {
        $storeId = $this->storeManager->getStore()->getId();
        if ($this->config->debugLogsEnabled($storeId)) {
            $logger = $this->generateLogger(self::ADYEN_DEBUG);
            return $logger->addRecord(Level::Debug, $message, $context);
        } else {
            return false;
        }
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function addAdyenWarning(string $message, array $context = []): bool
    {
        $logger = $this->generateLogger(self::ADYEN_WARNING);
        return $logger->addRecord(Level::Warning, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function addAdyenResult(string $message, array $context = []): bool
    {
        $logger = $this->generateLogger(self::ADYEN_RESULT);
        return $logger->addRecord(Level::Info, $message, $context);
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
    public function addAdyenInfoLog(string $message, array $context = []): bool
    {
        $logger = $this->generateLogger(self::ADYEN_INFO);
        return $logger->addRecord(Level::Info, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function error(string $message, array $context = []): bool
    {
        $logger = $this->generateLogger(self::ADYEN_ERROR);
        return $logger->addRecord(Level::Error, $message, $context);
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

    /**
     * @param int $handler
     * @return Logger
     */
    private function generateLogger(int $handler): Logger
    {
        /** @var Logger $logger */
        $logger = $this->loggerFactory->create(['name' => 'Adyen Logger']);

        foreach ($this->processors as $processor) {
            $logger->pushProcessor($processor);
        }

        switch ($handler) {
            case self::ADYEN_NOTIFICATION:
                $logger->pushHandler($this->handlers['adyenNotification']);
                break;
            case self::ADYEN_WARNING:
                $logger->pushHandler($this->handlers['adyenWarning']);
                break;
            case self::ADYEN_RESULT:
                $logger->pushHandler($this->handlers['adyenResult']);
                break;
            case self::ADYEN_INFO:
                $logger->pushHandler($this->handlers['adyenInfo']);
                break;
            case self::ADYEN_ERROR:
                $logger->pushHandler($this->handlers['adyenError']);
                break;
            case self::ADYEN_DEBUG:
            default:
                $logger->pushHandler($this->handlers['adyenDebug']);
                break;
        }

        return $logger;
    }
}

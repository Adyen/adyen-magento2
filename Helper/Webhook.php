<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\PaymentStates;
use Adyen\Webhook\Processor\ProcessorFactory;
use Magento\Sales\Model\Order;

class Webhook
{
    const WEBHOOK_ORDER_STATE_MAPPING = [
        Order::STATE_NEW => PaymentStates::STATE_NEW,
        Order::STATE_PROCESSING => PaymentStates::STATE_IN_PROGRESS
    ];

    /**
     * @var AdyenLogger
     */
    private $logger;

    public function __construct(AdyenLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Notification $notification
     * @param $orderState
     * @return string
     * @throws InvalidDataException
     */
    public function getTransitionState(Notification $notification, $orderState): string
    {
        $currentOrderState = $this->getCurrentState($orderState);
        $webhookNotificationItem = \Adyen\Webhook\Notification::createItem([
            'eventCode' => $notification->getEventCode(),
            'success' => $notification->getSuccess()
        ]);
        $processor = ProcessorFactory::create($webhookNotificationItem, $currentOrderState, $this->logger);

        return $processor->process();
    }

    public function getCurrentState($orderState)
    {
        return self::WEBHOOK_ORDER_STATE_MAPPING[$orderState] ?? null;
    }
}

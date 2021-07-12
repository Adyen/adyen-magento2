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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron;

use Adyen\Payment\Cron\Providers\OrdersProviderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Sales\Model\Service\OrderService;

class CancelOrders
{
    /**
     * @var OrdersProviderInterface[]
     */
    private $providers;
    /**
     * @var OrderService
     */
    private $orderService;
    /**
     * @var HistoryFactory
     */
    private $orderStatusHistoryFactory;

    /**
     * ServerIpAddress constructor.
     * @param array $providers
     */
    public function __construct(
        OrderService $orderService,
        HistoryFactory $orderStatusHistoryFactory,
        array $providers
    ) {
        $this->providers = $providers;
        $this->orderService = $orderService;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
    }

    public function execute()
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->provide() as $orderToCancel) {
                if ($this->orderService->cancel($orderToCancel->getEntityId())) {
                    $message = __('%1: Order has been cancelled', $provider->getProviderName());
                    $status = Order::STATE_CANCELED;
                } else {
                    $message = __('%1: Order could not be cancelled', $provider->getProviderName());
                    $status = $orderToCancel->getStatus();
                }
                $orderStatusHistory = $this->orderStatusHistoryFactory->create()
                    ->setParentId($orderToCancel->getEntityId())
                    ->setComment($message)
                    ->setEntityName('order')
                    ->setStatus($status);
                $this->orderService->addComment($orderToCancel->getEntityId(), $orderStatusHistory);
            }
        }
    }
}

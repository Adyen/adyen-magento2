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
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\Order;

class GetAdyenPaymentStatus implements ResolverInterface
{

    /**
     * @var DataProvider\GetAdyenPaymentStatus
     */
    protected $getAdyenPaymentStatusDataProvider;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @param DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider
     */
    public function __construct(
        DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider,
        Order $order
    ) {
        $this->getAdyenPaymentStatusDataProvider = $getAdyenPaymentStatusDataProvider;
        $this->order = $order;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (empty($args['orderId']) && empty($value['order_id'])) {
            throw new GraphQlInputException(__('Required parameter "order_id" is missing'));
        }

        if (isset($args['orderId'])) {
            $orderIncrementId = $args['orderId'];
        } else {
            $orderIncrementId = $value['order_id'];
        }

        $orderId = $this->order->loadByIncrementId($orderIncrementId)->getId();

        if (!$orderId) {
            throw new GraphQlNoSuchEntityException(__('Order does not exist'));
        }

        return $this->getAdyenPaymentStatusDataProvider->getGetAdyenPaymentStatus($orderId);
    }
}

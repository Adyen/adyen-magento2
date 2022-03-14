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
 * Copyright (c) 2022 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
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
     * @var GetCartForUser
     */
    protected $getCartForUser;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @param DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider
     * @param Order $order
     * @param GetCartForUser $getCartForUser
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider,
        Order $order,
        GetCartForUser $getCartForUser,
        AdyenLogger $adyenLogger
    ) {
        $this->getAdyenPaymentStatusDataProvider = $getAdyenPaymentStatusDataProvider;
        $this->order = $order;
        $this->getCartForUser = $getCartForUser;
        $this->adyenLogger = $adyenLogger;
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
        } elseif (empty($args['cartId']) && empty($value['cartId'])) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }

        if (isset($args['orderId'])) {
            $orderIncrementId = $args['orderId'];
        } else {
            $orderIncrementId = $value['order_id'];
        }
        $maskedCartId = $args['cartId'];

        $currentUserId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        try {
            $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
            $order = $this->order->loadByIncrementId($orderIncrementId);
            $orderId = $order->getId();

            if (!$orderId || $order->getQuoteId() !== $cart->getEntityId()) {
                throw new GraphQlNoSuchEntityException(__('Order does not exist'));
            }

            return $this->getAdyenPaymentStatusDataProvider->getGetAdyenPaymentStatus($orderId);

        } catch (GraphQlNoSuchEntityException $e) {
            $logMessage = isset($cart) ? sprintf('Attempted to get the payment status of order %s, using cart %s.', $orderIncrementId, $cart->getEntityId()) :
                sprintf('Attempted to get the payment status of order %s.', $orderIncrementId);
            $this->adyenLogger->addWarning($logMessage);

            throw $e;
        }
    }
}

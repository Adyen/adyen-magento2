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

use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
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
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var Quote
     */
    protected $quoteHelper;

    /**
     * @param DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider
     * @param Order $order
     * @param AdyenLogger $adyenLogger
     * @param Quote $quoteHelper
     */
    public function __construct(
        DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider,
        Order $order,
        AdyenLogger $adyenLogger,
        Quote $quoteHelper
    ) {
        $this->getAdyenPaymentStatusDataProvider = $getAdyenPaymentStatusDataProvider;
        $this->order = $order;
        $this->adyenLogger = $adyenLogger;
        $this->quoteHelper = $quoteHelper;
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
        if (empty($args['orderNumber']) && empty($value['order_number'])) {
            throw new GraphQlInputException(__('Required parameter "order_id" is missing'));
        } elseif (empty($args['cartId']) && empty($value['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        // Get the required values either from the passed arguments OR the query parameters (used in request chaining)
        $orderIncrementId = $args['orderNumber'] ?? $value['order_number'];
        $maskedCartId = $args['cartId'] ?? $value['cart_id'];

        $currentUserId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        try {
            $cart = $this->quoteHelper->getInactiveQuoteForUser($maskedCartId, $currentUserId, $storeId);
            $order = $this->order->loadByIncrementId($orderIncrementId);
            $orderId = $order->getId();

            if (!$orderId || $order->getQuoteId() !== $cart->getEntityId()) {
                throw new GraphQlNoSuchEntityException(__('Order does not exist'));
            }

            return $this->getAdyenPaymentStatusDataProvider->getGetAdyenPaymentStatus($orderId);

        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->addWarning(sprintf(
                'Attempted to get the payment status for order %s. Exception: %s',
                $orderIncrementId, $e->getMessage()
            ));

            throw new GraphQlNoSuchEntityException(__('Order does not exist'));
        }
    }
}

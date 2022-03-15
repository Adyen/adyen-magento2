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

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Sales\Model\Order;

class GetAdyenPaymentDetails implements ResolverInterface
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
     * @var Json
     */
    protected $jsonSerializer;

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
     * @param Json $jsonSerializer
     * @param GetCartForUser $getCartForUser
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider,
        Order $order,
        Json $jsonSerializer,
        GetCartForUser $getCartForUser,
        AdyenLogger $adyenLogger
    ) {
        $this->getAdyenPaymentStatusDataProvider = $getAdyenPaymentStatusDataProvider;
        $this->order = $order;
        $this->jsonSerializer = $jsonSerializer;
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
        if (empty($args['payload'])) {
            throw new GraphQlInputException(__('Required parameter "payload" is missing'));
        } elseif (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        $maskedCartId = $args['cart_id'];

        $currentUserId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        try {
            $payload = $this->jsonSerializer->unserialize($args['payload']);
            $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
            $order = $this->order->loadByIncrementId($payload['orderId']);
            $orderId = $order->getEntityId();

            if (is_null($orderId) || $order->getQuoteId() !== $cart->getEntityId()) {
                throw new GraphQlNoSuchEntityException(__('Order does not exist'));
            }
        } catch (GraphQlNoSuchEntityException $exception) {
            if (isset($payload) && array_key_exists('orderId', $payload)) {
                $this->adyenLogger->addWarning(sprintf('Attempted to get the payment details for order %s.', $payload['orderId']));
            }

            throw $exception;
        }

        // Set the orderId in the payload to the entity id, instead of the incrementId
        $payload['orderId'] = $order->getId();

        return $this->getAdyenPaymentStatusDataProvider->getGetAdyenPaymentDetails($this->jsonSerializer->serialize($payload));
    }
}

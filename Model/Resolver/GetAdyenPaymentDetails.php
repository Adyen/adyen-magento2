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

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use InvalidArgumentException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Sales\Model\Order;

class GetAdyenPaymentDetails implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;
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
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @param GetCartForUser $getCartForUser
     * @param DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider
     * @param Order $order
     * @param Json $jsonSerializer
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider,
        Order $order,
        Json $jsonSerializer,
        AdyenLogger $adyenLogger
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->getAdyenPaymentStatusDataProvider = $getAdyenPaymentStatusDataProvider;
        $this->order = $order;
        $this->jsonSerializer = $jsonSerializer;
        $this->adyenLogger = $adyenLogger;
    }


    /**
     * @inheritdoc
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|Value|mixed
     * @throws GraphQlAdyenException
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
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
        } catch (NoSuchEntityException $exception) {
            if (isset($payload) && array_key_exists('orderId', $payload)) {
                $this->adyenLogger->addWarning(sprintf('Attempted to get the payment details for order %s.', $payload['orderId']));
            }

            throw new GraphQlNoSuchEntityException(__('Order does not exist'));
        }

        // Set the orderId in the payload to the entity id, instead of the incrementId
        $payload['orderId'] = $order->getId();

        try {
            $payload = $this->jsonSerializer->unserialize($args['payload']);
            $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
            if (!array_key_exists('orderId', $payload)) {
                throw new GraphQlInputException(__('Invalid payload provided'));
            }
            $order = $this->order->loadByIncrementId($payload['orderId']);
            $orderId = $order->getEntityId();

            if (is_null($orderId) || $order->getQuoteId() !== $cart->getEntityId()) {
                throw new GraphQlNoSuchEntityException(__('Order does not exist'));
            }
        } catch (NoSuchEntityException $exception) {
            if (isset($payload) && array_key_exists('orderId', $payload)) {
                $this->adyenLogger->addError(sprintf('Attempted to get the payment details for order: %s.', $payload['orderId']));
            }

            throw new GraphQlNoSuchEntityException(__('Order does not exist'));
        } catch (InvalidArgumentException $exception) {
            throw new GraphQlInputException(__('Invalid payload provided'));
        }

        // Set the orderId in the payload to the entity id, instead of the incrementId
        $payload['orderId'] = $order->getId();

        try {
            return $this->getAdyenPaymentStatusDataProvider->getGetAdyenPaymentDetails($this->jsonSerializer->serialize($payload));
        } catch (Exception $exception) {
            $this->adyenLogger->addError(sprintf('GraphQl payment details call failed with error message: %s', $exception->getMessage()));
            // In the future, use the message and the code passed by the exception. Since currently the message and code are not
            // being passed, use this generic message.
            throw new GraphQlAdyenException(__('An unknown error has occurred'), null, 000);
        }
    }
}

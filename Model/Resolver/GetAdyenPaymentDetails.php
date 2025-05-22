<?php
/**
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
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Sales\Model\Order;
use Magento\GraphQl\Helper\Error\AggregateExceptionMessageFormatter;

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
        }
        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        $payload = $this->jsonSerializer->unserialize($args['payload']);
        if (!array_key_exists('orderId', $payload)) {
            throw new GraphQlInputException(__('Missing "orderId" from payload'));
        }

        $order = $this->order->loadByIncrementId($payload['orderId']);
        $maskedCartId = $args['cart_id'];
        $currentUserId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        if (is_null($order->getEntityId()) || $order->getQuoteId() !== $cart->getEntityId()) {
            throw new GraphQlNoSuchEntityException(__('Order does not exist'));
        }

        // Set the orderId in the payload to the entity id, instead of the incrementId
        $payload['orderId'] = $order->getId();

        try {
            return $this->getAdyenPaymentStatusDataProvider->getGetAdyenPaymentDetails(
                $this->jsonSerializer->serialize($payload),
                $order,
                $cart
            );
        } catch (\Exception $e) {
            $this->handleException($e, $order, $cart, $field, $context, $info);
        }
    }

    /**
     * @param $e
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @return mixed
     */
    private function getFormattedException($e, Field $field, ContextInterface $context, ResolveInfo $info)
    {
        if (class_exists(\Magento\QuoteGraphQl\Helper\Error\PlaceOrderMessageFormatter::class)) {
            $errorMessageFormatter = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\QuoteGraphQl\Helper\Error\PlaceOrderMessageFormatter::class);
            return $errorMessageFormatter->getFormatted(
                $e,
                __('Unable to place order: A server error stopped your order from being placed. ' .
                    'Please try to place your order again'),
                'Unable to place order',
                $field,
                $context,
                $info
            );
        }

        return new GraphQlAdyenException(__('Unable to place order: A server error stopped your order from being placed. ' .
            'Please try to place your order again'));
    }

    /**
     * @param \Exception $cause
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Quote\Api\Data\CartInterface $cart
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param \Magento\Framework\GraphQl\Schema\Type\ResolveInfo $info
     * @return void
     * @throws \Adyen\Payment\Exception\GraphQlAdyenException
     */
    public function handleException(
        \Exception $cause,
        Order $order,
        CartInterface $cart,
        Field $field,
        ContextInterface $context,
        ResolveInfo $info
    ): void {
        if ($cause instanceof LocalizedException) {
            throw $this->getFormattedException($cause, $field, $context, $info);
        }

        $this->adyenLogger->error(sprintf(
            'GraphQl payment details call failed with error message: %s',
            $cause->getMessage()
        ));

        // In the future, use the message and the code passed by the exception. Since currently the message and code are not
        // being passed, use this generic message.
        throw new GraphQlAdyenException(__('An unknown error has occurred'), null, 000);
    }
}

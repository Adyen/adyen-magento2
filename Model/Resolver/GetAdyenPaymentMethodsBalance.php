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
use Exception;
use Magento\Framework\Exception\LocalizedException;
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
use Magento\GraphQl\Helper\Error\AggregateExceptionMessageFormatter;
use Adyen\Payment\Helper\GiftcardPayment;

class GetAdyenRedeemedGiftcards implements ResolverInterface
{
    private GiftcardPayment $giftcardPayment;
    private GetCartForUser $getCartForUser;

    public function __construct(
        GiftcardPayment $giftcardPayment,
        GetCartForUser $getCartForUser
    ) {
        $this->giftcardPayment = $giftcardPayment;
        $this->getCartForUser = $getCartForUser;
    }

    public function resolve(
        Field $field,
              $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (empty($args['cartId'])) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }
        $cartId = (int) $args['cartId'];
        $userId = $context->getUserId();
        $storeId = (int) $context->getExtensionAttributes()->getStore()->getId();

        // Fetch cart for validation and use it in helper if needed
        $cart = $this->getCartForUser->execute($cartId, $userId, $storeId);

        $redeemedGiftcardsResponse = $this->giftcardPayment->fetchRedeemedGiftcards($cart->getId());

        // Assuming fetchRedeemedGiftcards returns JSON, decode it for GraphQL response
        $response = json_decode($redeemedGiftcardsResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error decoding the redeemed giftcards response');
        }

        return $response;
    }
}



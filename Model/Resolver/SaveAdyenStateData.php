<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Adyen\Payment\Api\AdyenStateDataInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class SaveAdyenStateData
{

    /**
     * @var GetCartForUser
     */
    protected GetCartForUser $cartForUser;

    /**
     * @var AdyenStateDataInterface
     */
    protected AdyenStateDataInterface $stateData;

    /**
     * SaveAdyenStateData Constructor
     *
     * @param GetCartForUser $cartForUser
     * @param AdyenStateDataInterface $stateData
     */
    public function __construct(
        GetCartForUser $cartForUser,
        AdyenStateDataInterface $stateData
    ) {
        $this->cartForUser = $cartForUser;
        $this->stateData = $stateData;
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {

        $cartId = $args['cart_id'] ?? null;
        if (!$cartId) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        $stateData = $args['state_data'] ?? null;
        if (!$stateData) {
            throw new GraphQlInputException('state_data');
        }

        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->cartForUser->execute($cartId, $context->getUserId(), $storeId);
        $this->stateData->save($stateData, (int)$cart->getId());

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }
}
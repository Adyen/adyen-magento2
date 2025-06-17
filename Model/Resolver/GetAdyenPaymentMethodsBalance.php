<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Model\Api\AdyenPaymentMethodsBalance;
use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class GetAdyenPaymentMethodsBalance implements ResolverInterface
{
    /**
     * @param AdyenPaymentMethodsBalance $balance
     */
    public function __construct(
        private readonly AdyenPaymentMethodsBalance $balance
    ) { }

    /**
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlAdyenException
     * @throws GraphQlInputException
     * @throws Exception
     */
    public function resolve(
        Field       $field,
                    $context,
        ResolveInfo $info,
        ?array       $value = null,
        ?array       $args = null
    ): array {
        if (empty($args['payload'])) {
            throw new GraphQlInputException(__('Required parameter "payload" is missing'));
        }

        $payload = $args['payload'];
        try {
            $balanceResponse = $this->balance->getBalance($payload);
        } catch (Exception $e) {
            throw new GraphQlAdyenException(
                __('An error occurred while fetching the payment method balance.'),
                $e
            );
        }

        return ['balanceResponse' => $balanceResponse];
    }
}



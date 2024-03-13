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
use Adyen\Payment\Model\Api\AdyenPaymentMethodsBalance;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;

class GetAdyenPaymentMethodsBalance implements ResolverInterface
{
    private AdyenPaymentMethodsBalance $balance;
    private Json $jsonSerializer;

    public function __construct(
        AdyenPaymentMethodsBalance  $balance,
        Json $jsonSerializer,
    )
    {
        $this->balance = $balance;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function resolve(
        Field       $field,
                    $context,
        ResolveInfo $info,
        array       $value = null,
        array       $args = null
    )
    {
        if (empty($args['payload'])) {
            throw new GraphQlInputException(__('Required parameter "payload" is missing'));
        }

        $payload = $args['payload'];
        try {
            $balanceResponse = $this->balance->getBalance($payload);
        } catch (LocalizedException $e) {
            throw new GraphQlAdyenException(__($e->getMessage()), $e);
        } catch (Exception $e) {
            throw new GraphQlAdyenException(__('An error occurred while fetching the payment method balance.'), $e);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error decoding the payment methods balance response');
        }

        return ['balance' => $balanceResponse];
    }
}



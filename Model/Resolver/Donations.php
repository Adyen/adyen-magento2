<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2026 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Model\Api\AdyenDonations;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;

class Donations extends AbstractDonationResolver
{
    /**
     * @return array
     */
    protected function getRequiredFields(): array
    {
        return [
            'cartId',
            'amount',
            'amount.currency',
            'returnUrl'
        ];
    }

    /**
     * @param OrderInterface $order
     * @param array $args
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @return array
     * @throws GraphQlAdyenException|LocalizedException
     */
    protected function performOperation(
        OrderInterface $order,
        array $args,
        Field $field,
        $context,
        ResolveInfo $info
    ): array {
        $payloadData = [
            'amount' => [
                'currency' => $args['amount']['currency'],
                'value' => $args['amount']['value']
            ],
            'returnUrl' => $args['returnUrl']
        ];

        $jsonSerializer = ObjectManager::getInstance()->get(Json::class);
        $payload = $jsonSerializer->serialize($payloadData);

        $adyenDonations = ObjectManager::getInstance()->get(AdyenDonations::class);
        $adyenDonations->makeDonation($payload, $order);

        return ['status' => true];
    }
}

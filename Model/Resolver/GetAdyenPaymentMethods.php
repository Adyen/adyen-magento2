<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class GetAdyenPaymentMethods implements ResolverInterface
{
    protected GetCartForUser $getCartForUser;
    protected PaymentMethods $paymentMethodsHelper;
    protected Json $jsonSerializer;
    protected AdyenLogger $adyenLogger;

    public function __construct(
        GetCartForUser $getCartForUser,
        PaymentMethods $paymentMethodsHelper,
        Json $jsonSerializer,
        AdyenLogger $adyenLogger
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->jsonSerializer = $jsonSerializer;
        $this->adyenLogger = $adyenLogger;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        $maskedCartId = $args['cart_id'];
        $shopperLocale = $args['shopper_locale'] ?? null;
        $country = $args['country'] ?? null;
        $channel = $args['channel'] ?? null;

        $currentUserId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        try {
            $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);

            $adyenPaymentMethodsResponse = $this->paymentMethodsHelper->getPaymentMethods(
                intval($cart->getId()),
                $country,
                $shopperLocale,
                $channel
            );

            return $adyenPaymentMethodsResponse ? $this->preparePaymentMethodGraphQlResponse($adyenPaymentMethodsResponse) : [];
        } catch (GraphQlAuthorizationException | GraphQlInputException | GraphQlNoSuchEntityException $exception) {
            $this->adyenLogger->error(sprintf('GraphQl payment methods call failed with error message: %s', $exception->getMessage()));
            throw $exception;
        } catch (Exception $exception) {
            $this->adyenLogger->error(sprintf('GraphQl payment methods call failed with error message: %s', $exception->getMessage()));
            // In the future, use the message and the code passed by the exception. Since currently the message and code are not
            // being passed, use this generic message.
            throw new GraphQlAdyenException(__('An unknown error has occurred'), null, 000);
        }
    }

    public function preparePaymentMethodGraphQlResponse(string $adyenPaymentMethodsResponse): array
    {
        $adyenPaymentMethodsResponse = $this->jsonSerializer->unserialize($adyenPaymentMethodsResponse);

        if (isset($adyenPaymentMethodsResponse['paymentMethodsExtraDetails'])) {
            //moved type from key to value because of graphql type limitations
            $extraDetails = [];
            foreach ($adyenPaymentMethodsResponse['paymentMethodsExtraDetails'] as $key => $paymentMethodsExtraDetails) {
                $paymentMethodsExtraDetails['type'] = $key;
                $extraDetails[] = $paymentMethodsExtraDetails;
            }
            $adyenPaymentMethodsResponse['paymentMethodsExtraDetails'] = $extraDetails;
        }
        return $adyenPaymentMethodsResponse;
    }
}

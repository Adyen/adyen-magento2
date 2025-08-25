<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\GuestAdyenPosCloud;
use Adyen\Payment\Model\Resolver\DataProvider\GetAdyenPaymentStatus;
use Adyen\Payment\Model\Sales\OrderRepository;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class InitiatePosPayment implements ResolverInterface
{
    /**
     * @param AdyenLogger $adyenLogger
     * @param GuestAdyenPosCloud $adyenPosCloud
     * @param GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        private readonly AdyenLogger $adyenLogger,
        private readonly GuestAdyenPosCloud  $adyenPosCloud,
        private readonly DataProvider\GetAdyenPaymentStatus $getAdyenPaymentStatusDataProvider,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly OrderRepository $orderRepository
    ) {}

    /**
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlAdyenException
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        // Get the required values
        $maskedCartId = (string) $args['cartId'];

        if (empty($maskedCartId)) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }

        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($maskedCartId);
        } catch (NoSuchEntityException $e) {
            $errorMessage = sprintf("Quote with masked ID %s not found!", $maskedCartId);
            $this->adyenLogger->error($errorMessage);

            throw new GraphQlAdyenException(
                __("The entity that was requested doesn't exist. Verify the entity and try again.")
            );
        }

        try {
            $order = $this->orderRepository->getOrderByQuoteId($quoteId);
            $this->adyenPosCloud->pay(intval($order->getEntityId()));

            return $this->getAdyenPaymentStatusDataProvider->getGetAdyenPaymentStatus(strval($order->getEntityId()));
        } catch (Exception $e) {
            $errorMessage = 'An error occurred while initiating POS payment.';
            $this->adyenLogger->error(sprintf("%s %s", $errorMessage, $e->getMessage()));

            throw new GraphQlAdyenException(__($errorMessage));
        }
    }
}

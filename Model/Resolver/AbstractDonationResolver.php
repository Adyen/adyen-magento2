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
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\GraphqlInputArgumentValidator;
use Adyen\Payment\Model\Sales\OrderRepository;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Helper\Error\AggregateExceptionMessageFormatter;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Sales\Api\Data\OrderInterface;

abstract class AbstractDonationResolver implements ResolverInterface
{
    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param OrderRepository $orderRepository
     * @param GraphqlInputArgumentValidator $graphqlInputArgumentValidator
     * @param AdyenLogger $adyenLogger
     * @param AggregateExceptionMessageFormatter $adyenGraphQlExceptionMessageFormatter
     */
    public function __construct(
        protected readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        protected readonly OrderRepository $orderRepository,
        protected readonly GraphqlInputArgumentValidator $graphqlInputArgumentValidator,
        protected readonly AdyenLogger $adyenLogger,
        protected readonly AggregateExceptionMessageFormatter $adyenGraphQlExceptionMessageFormatter
    ) { }

    /**
     * @return array
     */
    abstract protected function getRequiredFields(): array;

    /**
     * @param OrderInterface $order
     * @param array $args
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @return array
     * @throws GraphQlAdyenException
     */
    abstract protected function performOperation(
        OrderInterface $order,
        array $args,
        Field $field,
        $context,
        ResolveInfo $info
    ): array;

    /**
     * @return string
     */
    protected function getGenericErrorMessage(): string
    {
        return 'An error occurred while processing the donation.';
    }

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
        $this->graphqlInputArgumentValidator->execute($args, $this->getRequiredFields());

        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($args['cartId']);
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error(sprintf("Quote with masked ID %s not found!", $args['cartId']));
            throw new GraphQlAdyenException(__($this->getGenericErrorMessage()));
        }

        $order = $this->orderRepository->getOrderByQuoteId($quoteId);

        if (!$order) {
            $this->adyenLogger->error(sprintf("Order for quote ID %s not found!", $quoteId));
            throw new GraphQlAdyenException(__($this->getGenericErrorMessage()));
        }

        try {
            return $this->performOperation($order, $args, $field, $context, $info);
        } catch (LocalizedException $e) {
            throw $this->adyenGraphQlExceptionMessageFormatter->getFormatted(
                $e,
                __($this->getGenericErrorMessage()),
                $this->getGenericErrorMessage(),
                $field,
                $context,
                $info
            );
        } catch (Exception $e) {
            $this->adyenLogger->error(sprintf(
                '%s: %s',
                $this->getGenericErrorMessage(),
                $e->getMessage()
            ));
            throw new GraphQlAdyenException(__($this->getGenericErrorMessage()));
        }
    }
}

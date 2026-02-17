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
use Adyen\Payment\Model\Api\AdyenDonations;
use Adyen\Payment\Model\GraphqlInputArgumentValidator;
use Adyen\Payment\Model\Sales\OrderRepository;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\GraphQl\Helper\Error\AggregateExceptionMessageFormatter;

class Donations implements ResolverInterface
{
    private const REQUIRED_FIELDS = [
        'cartId',
        'amount',
        'amount.currency',
        'returnUrl'
    ];

    /**
     * @param AdyenDonations $adyenDonations
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param OrderRepository $orderRepository
     * @param Json $jsonSerializer
     * @param GraphqlInputArgumentValidator $graphqlInputArgumentValidator
     * @param AdyenLogger $adyenLogger
     * @param AggregateExceptionMessageFormatter $adyenGraphQlExceptionMessageFormatter
     */
    public function __construct(
        private readonly AdyenDonations $adyenDonations,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly OrderRepository $orderRepository,
        private readonly Json $jsonSerializer,
        private readonly GraphqlInputArgumentValidator $graphqlInputArgumentValidator,
        private readonly AdyenLogger $adyenLogger,
        private readonly AggregateExceptionMessageFormatter $adyenGraphQlExceptionMessageFormatter
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
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $this->graphqlInputArgumentValidator->execute($args, self::REQUIRED_FIELDS);

        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($args['cartId']);
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error(sprintf("Quote with masked ID %s not found!", $args['cartId']));
            throw new GraphQlAdyenException(__('An error occurred while processing the donation.'));
        }

        $order = $this->orderRepository->getOrderByQuoteId($quoteId);

        if (!$order) {
            $this->adyenLogger->error(sprintf("Order for quote ID %s not found!", $quoteId));
            throw new GraphQlAdyenException(__('An error occurred while processing the donation.'));
        }

        $payloadData = [
            'amount' => [
                'currency' => $args['amount']['currency'],
                'value' => $args['amount']['value']
            ],
            'returnUrl' => $args['returnUrl']
        ];

        $payload = $this->jsonSerializer->serialize($payloadData);

        try {
            $this->adyenDonations->makeDonation($payload, $order);
        } catch (LocalizedException $e) {
            throw $this->adyenGraphQlExceptionMessageFormatter->getFormatted(
                $e,
                __('Donation failed!'),
                'Unable to donate',
                $field,
                $context,
                $info
            );
        } catch (Exception $e) {
            $this->adyenLogger->error(sprintf(
                'GraphQl donation call failed with error message: %s',
                $e->getMessage()
            ));
            throw new GraphQlAdyenException(__('An error occurred while processing the donation.'));
        }

        return ['status' => true];
    }
}

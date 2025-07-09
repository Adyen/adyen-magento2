<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\AdyenStateData;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class RemoveAdyenStateData implements ResolverInterface
{
    /**
     * @param AdyenStateData $adyenStateData
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly AdyenStateData $adyenStateData,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly AdyenLogger $adyenLogger
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
     * @throws LocalizedException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        if (empty($args['stateDataId'])) {
            throw new GraphQlInputException(__('Required parameter "stateDataId" is missing'));
        }

        if (empty($args['cartId'])) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }

        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($args['cartId']);
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error(sprintf("Quote with masked ID %s not found!", $args['cartId']));
            throw new GraphQlAdyenException(__("An error occurred while removing the state data."));
        }

        try {
            $result = $this->adyenStateData->remove((int) $args['stateDataId'], $quoteId);
        } catch (Exception $e) {
            $this->adyenLogger->error(sprintf(
                "An error occurred while removing the state data: %s",
                $e->getMessage()
            ));
            throw new GraphQlAdyenException(__('An error occurred while removing the state data.'));
        }

        if (!$result) {
            throw new LocalizedException(__('An error occurred while removing the state data.'));
        }

        return ['stateDataId' => $args['stateDataId']];
    }
}

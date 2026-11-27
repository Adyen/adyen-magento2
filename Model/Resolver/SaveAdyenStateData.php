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
use Adyen\Payment\Helper\Quote as QuoteHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\AdyenStateData;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class SaveAdyenStateData implements ResolverInterface
{
    /**
     * @param AdyenStateData $adyenStateData
     * @param QuoteHelper $quoteHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly AdyenStateData $adyenStateData,
        private readonly QuoteHelper $quoteHelper,
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
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        if (empty($args['stateData'])) {
            throw new GraphQlInputException(__('Required parameter "stateData" is missing'));
        }

        if (empty($args['cartId'])) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }

        try {
            $quoteId = (int) $this->quoteHelper->getCartForUser(
                $args['cartId'],
                $context->getUserId(),
                (int) $context->getExtensionAttributes()->getStore()->getId()
            )->getId();
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error(sprintf(
                "Unable to resolve cart with masked ID %s: %s",
                $args['cartId'],
                $e->getMessage()
            ));
            throw new GraphQlAdyenException(__("An error occurred while saving the state data."));
        }

        try {
            $stateDataId = $this->adyenStateData->save($args['stateData'], $quoteId);
        } catch (Exception $e) {
            throw new GraphQlAdyenException(__('An error occurred while saving the state data.'), $e);
        }

        return ['stateDataId' => $stateDataId];
    }
}

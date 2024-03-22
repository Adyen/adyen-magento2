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
use Adyen\Payment\Model\Api\AdyenStateData;
use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\QuoteIdMaskFactory;

class SaveAdyenStateData implements ResolverInterface
{
    /**
     * @var AdyenStateData
     */
    private AdyenStateData $adyenStateData;

    /**
     * @var QuoteIdMaskFactory
     */
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    /**
     * @param AdyenStateData $adyenStateData
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        AdyenStateData $adyenStateData,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->adyenStateData = $adyenStateData;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
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
        array $value = null,
        array $args = null
    ): array {
        if (empty($args['stateData'])) {
            throw new GraphQlInputException(__('Required parameter "stateData" is missing'));
        }

        if (empty($args['cartId'])) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($args['cartId'], 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        try {
            $stateDataId = $this->adyenStateData->save($args['stateData'], (int) $quoteId);
        } catch (Exception $e) {
            throw new GraphQlAdyenException(__('An error occurred while saving the state data.'), $e);
        }

        return ['stateDataId' => $stateDataId];
    }
}



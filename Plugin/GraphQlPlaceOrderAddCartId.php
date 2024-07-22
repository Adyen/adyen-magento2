<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Plugin;
use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\QuoteGraphQl\Model\Resolver\PlaceOrder;

class GraphQlPlaceOrderAddCartId
{
    /**
     * @var Quote
     */
    private $quoteHelper;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;

    /**
     * GraphQlPlaceOrderAddCartId constructor.
     * @param Quote $quoteHelper
     * @param AdyenLogger $adyenLogger
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     */
    public function __construct(
        Quote $quoteHelper,
        AdyenLogger $adyenLogger,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
    )
    {
        $this->quoteHelper = $quoteHelper;
        $this->adyenLogger = $adyenLogger;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
    }

    /**
     * This function adds the masked cart_id to the output of PlaceOrder::resolve
     *
     * @param PlaceOrder $placeOrder
     * @param array $result
     * @return array
     */
    public function afterResolve(PlaceOrder $placeOrder, array $result): array
    {
        if (!isset($result['order']) || !isset($result['order']['order_number'])) {
            $this->adyenLogger->error('Order information not found in PlaceOrder result');
            return $result;
        }

        try {
            $cart = $this->quoteHelper->getQuoteByOrderIncrementId($result['order']['order_number']);
            $maskedId = $this->quoteIdToMaskedQuoteId->execute($cart->getId());
            $result['order']['cart_id'] = $maskedId;
        } catch (NoSuchEntityException $exception) {
            $this->adyenLogger->error('Error retrieving cart: ' . $exception->getMessage());
        }

        return $result;
    }
}

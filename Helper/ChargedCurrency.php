<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\AdyenAmountCurrency;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;

class ChargedCurrency
{
    /**
     * @var string
     * Charged currency value when Global/Website is selected
     */
    const BASE = "base";

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @param Order $order
     * @return AdyenAmountCurrency
     */
    public function getOrderAmountCurrency(Order $order)
    {
        $chargedCurrency = $this->config->getChargedCurrency($order->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency($order->getBaseGrandTotal(), $order->getGlobalCurrencyCode());
        }
        return new AdyenAmountCurrency($order->getGrandTotal(), $order->getOrderCurrencyCode());
    }

    /**
     * @param Quote\Item $item
     * @return AdyenAmountCurrency
     */
    public function getItemAmountCurrency(Quote\Item $item)
    {
        $chargedCurrency = $this->config->getChargedCurrency($item->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $item->getBasePrice(),
                $item->getQuote()->getBaseCurrencyCode(),
                $item->getBaseDiscountAmount(),
                ($item->getTaxPercent() / 100) * $item->$item->getBasePrice()
            );
        }
        return new AdyenAmountCurrency(
            $item->getRowTotal() / $item->getQty(),
            $item->getQuote()->getQuoteCurrencyCode(),
            $item->getDiscountAmount(),
            ($item->getTaxPercent() / 100) * ($item->getRowTotal() / $item->getQty())
        );
    }

    /**
     * @param Quote $quote
     * @return AdyenAmountCurrency
     */
    public function getQuoteAmountCurrency(Quote $quote)
    {
        $chargedCurrency = $this->config->getChargedCurrency($quote->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency($quote->getBaseGrandTotal(), $quote->getBaseCurrencyCode());
        }
        return new AdyenAmountCurrency($quote->getGrandTotal(), $quote->getQuoteCurrencyCode());
    }

    /**
     * @param Store $store
     * @return string|null
     */
    public function getStoreAmountCurrency(Store $store)
    {
        $chargedCurrency = $this->config->getChargedCurrency($store->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return $store->getBaseCurrencyCode();
        }
        return $store->getCurrentCurrencyCode();
    }

    /**
     * @param Order $order
     * @return string|null
     */
    public function getRefundCurrencyCode(Order $order)
    {
        $chargedCurrency = $order->getAdyenChargedCurrency();
        if (empty($chargedCurrency)) {
            return $order->getOrderCurrencyCode();
        }
        return $chargedCurrency;
    }
}

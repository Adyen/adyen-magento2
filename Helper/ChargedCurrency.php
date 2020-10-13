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

    public function getOrderAmountCurrency(Order $order)
    {
        $chargedCurrency = $this->config->getChargedCurrency($order->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency($order->getBaseGrandTotal(), $order->getGlobalCurrencyCode());
        }
        return new AdyenAmountCurrency($order->getGrandTotal(), $order->getOrderCurrencyCode());
    }

    public function getItemAmountCurrency(Quote\Item $item)
    {
        $chargedCurrency = $this->config->getChargedCurrency($item->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency($item->getPrice(), $item->getQuote()->getBaseCurrencyCode());
        }
        return new AdyenAmountCurrency($item->getPrice(), $item->getQuote()->getQuoteCurrencyCode());
    }

    public function getQuoteAmountCurrency(Quote $quote)
    {
        $chargedCurrency = $this->config->getChargedCurrency($quote->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency($quote->getBaseGrandTotal(), $quote->getBaseCurrencyCode());
        }
        return new AdyenAmountCurrency($quote->getGrandTotal(), $quote->getQuoteCurrencyCode());
    }

    public function getStoreAmountCurrency(Store $store)
    {
        $chargedCurrency = $this->config->getChargedCurrency($store->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return $store->getBaseCurrencyCode();
        }
        return $store->getCurrentCurrencyCode();
    }

    public function getRefundCurrencyCode(Order $order)
    {
        $chargedCurrency = $order->getAdyenChargedCurrency();
        if (empty($chargedCurrency)) {
            return $order->getOrderCurrencyCode();
        }
        return $chargedCurrency;
    }
}

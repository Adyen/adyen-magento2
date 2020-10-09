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

    public function getOrderCurrencyCode(Order $order)
    {
        $chargedCurrency = $this->config->getChargedCurrency($order->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return $order->getGlobalCurrencyCode();
        }
        return $order->getOrderCurrencyCode();
    }

    public function getQuoteCurrencyCode(Quote $quote)
    {
        $chargedCurrency = $this->config->getChargedCurrency($quote->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return $quote->getBaseCurrencyCode();
        }
        return $quote->getQuoteCurrencyCode();
    }

    public function getStoreCurrencyCode(Store $store)
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

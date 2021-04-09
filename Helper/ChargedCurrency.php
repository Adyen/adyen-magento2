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
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Api\Data\CreditmemoItemInterface;

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
     * @param bool $orderPlacement true if fetching the order's data when it is being placed,
     * false to get the data according to the charged_currency already saved for the order
     *
     * @return AdyenAmountCurrency
     */
    public function getOrderAmountCurrency(Order $order, bool $orderPlacement = true)
    {
        $chargedCurrency = $orderPlacement
            ? $this->config->getChargedCurrency($order->getStoreId())
            : $order->getAdyenChargedCurrency();
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $order->getBaseGrandTotal(),
                $order->getGlobalCurrencyCode(),
                null,
                null,
                $order->getBaseTotalDue()
            );
        }
        return new AdyenAmountCurrency(
            $order->getGrandTotal(),
            $order->getOrderCurrencyCode(),
            null,
            null,
            $order->getTotalDue()
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
     * @param Quote\Item $item
     * @return AdyenAmountCurrency
     */
    public function getQuoteItemAmountCurrency(Quote\Item $item)
    {
        $chargedCurrency = $this->config->getChargedCurrency($item->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $item->getBasePrice(),
                $item->getQuote()->getBaseCurrencyCode(),
                $item->getBaseDiscountAmount(),
                $item->getBaseTaxAmount()
            );
        }
        return new AdyenAmountCurrency(
            $item->getRowTotal() / $item->getQty(),
            $item->getQuote()->getQuoteCurrencyCode(),
            $item->getDiscountAmount(),
            $item->getTaxAmount()
        );
    }

    /**
     * @param Invoice\Item $item
     * @return AdyenAmountCurrency
     */
    public function getInvoiceItemAmountCurrency(Invoice\Item $item)
    {
        $chargedCurrency = $item->getInvoice()->getOrder()->getAdyenChargedCurrency();
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $item->getBasePrice(),
                $item->getInvoice()->getBaseCurrencyCode(),
                null,
                $item->getBaseTaxAmount()
            );
        }
        return new AdyenAmountCurrency(
            $item->getPrice(),
            $item->getInvoice()->getOrderCurrencyCode(),
            null,
            $item->getTaxAmount()
        );
    }

    /**
     * @param CreditmemoItemInterface $item
     * @return AdyenAmountCurrency
     */
    public function getCreditMemoItemAmountCurrency(CreditmemoItemInterface $item)
    {
        $chargedCurrency = $item->getCreditMemo()->getInvoice()->getOrder()->getAdyenChargedCurrency();
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $item->getBasePrice(),
                $item->getCreditMemo()->getInvoice()->getBaseCurrencyCode(),
                null,
                $item->getBaseTaxAmount()
            );
        }
        return new AdyenAmountCurrency(
            $item->getPrice(),
            $item->getCreditMemo()->getInvoice()->getOrderCurrencyCode(),
            null,
            $item->getTaxAmount()
        );
    }

    /**
     * @param Quote $quote
     * @return AdyenAmountCurrency
     */
    public function getQuoteShippingAmountCurrency(Quote $quote)
    {
        $chargedCurrency = $this->config->getChargedCurrency($quote->getStoreId());
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $quote->getShippingAddress()->getBaseShippingAmount(),
                $quote->getBaseCurrencyCode(),
                $quote->getShippingAddress()->getBaseShippingDiscountAmount(),
                $quote->getShippingAddress()->getBaseShippingTaxAmount()
            );
        }
        return new AdyenAmountCurrency(
            $quote->getShippingAddress()->getShippingAmount(),
            $quote->getQuoteCurrencyCode(),
            $quote->getShippingAddress()->getShippingDiscountAmount(),
            $quote->getShippingAddress()->getShippingTaxAmount()
        );
    }

    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @return AdyenAmountCurrency
     */
    public function getInvoiceShippingAmountCurrency(Invoice $invoice)
    {
        $chargedCurrency = $invoice->getOrder()->getAdyenChargedCurrency();
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $invoice->getBaseShippingAmount(),
                $invoice->getBaseCurrencyCode(),
                null,
                $invoice->getBaseShippingTaxAmount()
            );
        }
        return new AdyenAmountCurrency(
            $invoice->getShippingAmount(),
            $invoice->getOrderCurrencyCode(),
            null,
            $invoice->getShippingTaxAmount()
        );
    }
}

<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\AdyenAmountCurrency;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Weee\Block\Adminhtml\Items\Price\Renderer;

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

    /**
     * @var Renderer
     */
    private $weeeRenderer;

    public function __construct(
        Config $config,
        Renderer $weeeRenderer
    ) {
        $this->config = $config;
        $this->weeeRenderer = $weeeRenderer;
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

    public function getQuoteItemAmountCurrency(Quote\Item $item): AdyenAmountCurrency
    {
        $chargedCurrency = $this->config->getChargedCurrency($item->getStoreId());

        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $item->getBaseRowTotal() / $item->getQty(),
                $item->getQuote()->getBaseCurrencyCode(),
                $item->getBaseDiscountAmount() / $item->getQty(),
                $item->getBaseTaxAmount() / $item->getQty(),
                null,
                $this->weeeRenderer->getBaseTotalAmount($item)
            );
        }

        return new AdyenAmountCurrency(
            $item->getRowTotal() / $item->getQty(),
            $item->getQuote()->getQuoteCurrencyCode(),
            $item->getDiscountAmount() / $item->getQty(),
            $item->getTaxAmount() / $item->getQty(),
            null,
            $this->weeeRenderer->getTotalAmount($item)
        );
    }

    public function getInvoiceItemAmountCurrency(Invoice\Item $item): AdyenAmountCurrency
    {
        $chargedCurrency = $item->getInvoice()->getOrder()->getAdyenChargedCurrency();

        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $item->getBaseRowTotal() / $item->getQty(),
                $item->getInvoice()->getBaseCurrencyCode(),
                $item->getBaseDiscountAmount() / $item->getQty(),
                $item->getBaseTaxAmount() / $item->getQty(),
                null,
                $this->weeeRenderer->getBaseTotalAmount($item)
            );
        }

        return new AdyenAmountCurrency(
            $item->getRowTotal() / $item->getQty(),
            $item->getInvoice()->getOrderCurrencyCode(),
            $item->getDiscountAmount() / $item->getQty(),
            $item->getTaxAmount() / $item->getQty(),
            null,
            $this->weeeRenderer->getTotalAmount($item)
        );
    }

    /**
     * @param CreditmemoInterface $creditMemo
     * @return AdyenAmountCurrency
     */
    public function getCreditMemoAmountCurrency(CreditmemoInterface $creditMemo)
    {
        $chargedCurrency = $creditMemo->getOrder()->getAdyenChargedCurrency();
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $creditMemo->getBaseGrandTotal(),
                $creditMemo->getBaseCurrencyCode(),
                null,
                $creditMemo->getBaseTaxAmount()
            );
        }
        return new AdyenAmountCurrency(
            $creditMemo->getGrandTotal(),
            $creditMemo->getOrderCurrencyCode(),
            null,
            $creditMemo->getTaxAmount()
        );
    }


    /**
     * @param CreditmemoInterface $creditMemo
     * @return AdyenAmountCurrency
     */
    public function getCreditMemoAdjustmentAmountCurrency(CreditmemoInterface $creditMemo)
    {
        $chargedCurrency = $creditMemo->getOrder()->getAdyenChargedCurrency();
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $creditMemo->getBaseAdjustment(),
                $creditMemo->getBaseCurrencyCode()
            );
        }
        return new AdyenAmountCurrency(
            $creditMemo->getAdjustment(),
            $creditMemo->getOrderCurrencyCode()
        );
    }

    public function getCreditMemoShippingAmountCurrency(CreditmemoInterface $creditMemo): AdyenAmountCurrency
    {
        $chargedCurrency = $creditMemo->getOrder()->getAdyenChargedCurrency();

        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $creditMemo->getBaseShippingAmount(),
                $creditMemo->getBaseCurrencyCode(),
                null,
                $creditMemo->getBaseShippingTaxAmount(),
                null,
                $creditMemo->getBaseShippingInclTax()
            );
        }
        return new AdyenAmountCurrency(
            $creditMemo->getShippingAmount(),
            $creditMemo->getOrderCurrencyCode(),
            null,
            $creditMemo->getShippingTaxAmount(),
            null,
            $creditMemo->getShippingInclTax()
        );
    }

    public function getCreditMemoItemAmountCurrency(CreditmemoItemInterface $item): AdyenAmountCurrency
    {
        $chargedCurrency = $item->getCreditMemo()->getOrder()->getAdyenChargedCurrency();

        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $item->getBaseRowTotal() / $item->getQty(),
                $item->getCreditMemo()->getBaseCurrencyCode(),
                $item->getBaseDiscountAmount() / $item->getQty(),
                $item->getBaseTaxAmount() / $item->getQty(),
                null,
                $this->weeeRenderer->getBaseTotalAmount($item)
            );
        }
        return new AdyenAmountCurrency(
            $item->getRowTotal() / $item->getQty(),
            $item->getCreditMemo()->getOrderCurrencyCode(),
            $item->getDiscountAmount() / $item->getQty(),
            $item->getTaxAmount() / $item->getQty(),
            null,
            $this->weeeRenderer->getTotalAmount($item)
        );
    }

    public function getQuoteShippingAmountCurrency(Quote $quote): AdyenAmountCurrency
    {
        $chargedCurrency = $this->config->getChargedCurrency($quote->getStoreId());

        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $quote->getShippingAddress()->getBaseShippingAmount(),
                $quote->getBaseCurrencyCode(),
                $quote->getShippingAddress()->getBaseShippingDiscountAmount(),
                $quote->getShippingAddress()->getBaseShippingTaxAmount(),
                null,
                $quote->getShippingAddress()->getBaseShippingInclTax()
            );
        }

        return new AdyenAmountCurrency(
            $quote->getShippingAddress()->getShippingAmount(),
            $quote->getQuoteCurrencyCode(),
            $quote->getShippingAddress()->getShippingDiscountAmount(),
            $quote->getShippingAddress()->getShippingTaxAmount(),
            null,
            $quote->getShippingAddress()->getShippingInclTax()
        );
    }

    public function getInvoiceShippingAmountCurrency(Invoice $invoice): AdyenAmountCurrency
    {
        $chargedCurrency = $invoice->getOrder()->getAdyenChargedCurrency();

        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $invoice->getBaseShippingAmount(),
                $invoice->getBaseCurrencyCode(),
                null,
                $invoice->getBaseShippingTaxAmount(),
                null,
                $invoice->getBaseShippingInclTax()
            );
        }

        return new AdyenAmountCurrency(
            $invoice->getShippingAmount(),
            $invoice->getOrderCurrencyCode(),
            null,
            $invoice->getShippingTaxAmount(),
            null,
            $invoice->getShippingInclTax()
        );
    }

    /**
     * @param Invoice $invoice
     * @return AdyenAmountCurrency
     */
    public function getInvoiceAmountCurrency(Invoice $invoice)
    {
        $chargedCurrency = $invoice->getOrder()->getAdyenChargedCurrency();
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $invoice->getBaseGrandTotal(),
                $invoice->getBaseCurrencyCode()
            );
        }
        return new AdyenAmountCurrency(
            $invoice->getGrandTotal(),
            $invoice->getOrderCurrencyCode()
        );

    }
}

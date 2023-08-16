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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use \Magento\Framework\App\Config\Value;

class ChargedCurrency
{
    /**
     * @var string
     * Charged currency value when Global/Website is selected
     */
    const BASE = "base";
    const DISCOUNT_TAX_PATH = "tax/calculation/discount_tax";

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Value
     */
    private $configValue;

    public function __construct(
        Config $config,
        ScopeConfigInterface $scopeConfig,
        Value $configValue
    ) {
        $this->config = $config;
        $this->scopeConfig = $scopeConfig;
        $this->configValue = $configValue;
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
                $item->getBasePriceInclTax() - $item->getBasePrice(),
                null,
                $item->getBasePriceInclTax()
            );
        }

        $amount = $item->getRowTotal()/$item->getQty();
        $amountIncludingTax = $item->getRowTotalInclTax()/$item->getQty();
        $amountExcludingTax = $item->getRowTotal()/$item->getQty();
        $taxAmountUs = ($amountExcludingTax - ($item->getDiscountAmount()/$item->getQty())) * $item->getTaxPercent()/100;
        $taxAmountRw = $amountIncludingTax - $amount;
        $pathExists = $this->isPathExists(self::DISCOUNT_TAX_PATH);


        if ($pathExists) {
            $applyDiscountOnPrice = $this->getApplyDiscountOnPriceConfig();
            $totalAmount = $applyDiscountOnPrice ? $amountIncludingTax : $amountExcludingTax;
            $calculatedTax = $applyDiscountOnPrice ? $taxAmountRw : $taxAmountUs;
        } else {
            $totalAmount = $amountIncludingTax;
            $calculatedTax = $taxAmountRw;
        }

        return new AdyenAmountCurrency(
            $amount,
            $item->getQuote()->getQuoteCurrencyCode(),
            $item->getDiscountAmount(),
            $calculatedTax,
            null,
            $totalAmount
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
                $item->getBaseTaxAmount() / $item->getQty()
            );
        }
        return new AdyenAmountCurrency(
            $item->getPrice(),
            $item->getInvoice()->getOrderCurrencyCode(),
            null,
            ($item->getQty() > 0) ? $item->getTaxAmount() / $item->getQty() : 0
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

    /**
     * @param CreditmemoInterface $creditMemo
     * @return AdyenAmountCurrency
     */
    public function getCreditMemoShippingAmountCurrency(CreditmemoInterface $creditMemo)
    {
        $chargedCurrency = $creditMemo->getOrder()->getAdyenChargedCurrency();
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $creditMemo->getBaseShippingAmount(),
                $creditMemo->getBaseCurrencyCode(),
                null,
                $creditMemo->getBaseShippingTaxAmount()
            );
        }
        return new AdyenAmountCurrency(
            $creditMemo->getShippingAmount(),
            $creditMemo->getOrderCurrencyCode(),
            null,
            $creditMemo->getShippingTaxAmount()
        );
    }

    /**
     * @param CreditmemoItemInterface $item
     * @return AdyenAmountCurrency
     */
    public function getCreditMemoItemAmountCurrency(CreditmemoItemInterface $item)
    {
        $chargedCurrency = $item->getCreditMemo()->getOrder()->getAdyenChargedCurrency();
        if ($chargedCurrency == self::BASE) {
            return new AdyenAmountCurrency(
                $item->getBasePrice(),
                $item->getCreditMemo()->getBaseCurrencyCode(),
                null,
                $item->getBaseTaxAmount() / $item->getQty()
            );
        }
        return new AdyenAmountCurrency(
            $item->getPrice(),
            $item->getCreditMemo()->getOrderCurrencyCode(),
            null,
            $item->getTaxAmount() / $item->getQty()
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

    /**
     * @param Invoice $invoice
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

    /**
     * @param ScopeInterface
     * @return bool
     */
    public function getApplyDiscountOnPriceConfig()
    {
        return $this->scopeConfig->getValue(
            'tax/calculation/discount_tax',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @param string $path
     * @param string $scope
     * @return bool
     */
    public function isPathExists(string $path, string $scope = 'default')
    {
        $collection = $this->configValue->getCollection()
            ->addFieldToFilter('path', $path)
            ->addFieldToFilter('scope', $scope);

        return $collection->getSize() > 0;
    }
}

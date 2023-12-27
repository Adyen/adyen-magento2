<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\AdyenAmountCurrency;
use Magento\Catalog\Helper\Image;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Invoice\Item;
use Magento\Quote\Model\Quote;

class OpenInvoice
{
    protected Data $adyenHelper;
    protected CartRepositoryInterface $cartRepository;
    protected ChargedCurrency $chargedCurrency;
    protected Config $configHelper;
    protected Image $imageHelper;

    public function __construct(
        Data                    $adyenHelper,
        CartRepositoryInterface $cartRepository,
        ChargedCurrency         $chargedCurrency,
        Config                  $configHelper,
        Image                   $imageHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->cartRepository = $cartRepository;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
        $this->imageHelper = $imageHelper;
    }

    public function getOpenInvoiceDataForLastInvoice(Payment $payment): array
    {
        $formFields = ['lineItems' => []];
        $order = $payment->getOrder();
        $invoices = $order->getInvoiceCollection();
        // The latest invoice will contain only the selected items(and quantities) for the (partial) capture
        /** @var Invoice $invoice */
        $invoice = $invoices->getLastItem();
        $discountAmount = 0;
        $currency = $this->chargedCurrency->getOrderAmountCurrency($payment->getOrder(), false);

        /* @var Item $invoiceItem */
        foreach ($invoice->getItems() as $invoiceItem) {
            $numberOfItems = (int)$invoiceItem->getQty();
            $orderItem = $invoiceItem->getOrderItem();
            if ($orderItem->getParentItem() || $numberOfItems <= 0) {
                continue;
            }
            $product = $orderItem->getProduct();
            $itemAmountCurrency = $this->chargedCurrency->getInvoiceItemAmountCurrency($invoiceItem);
            $discountAmount += $itemAmountCurrency->getDiscountAmount();
            $formFields['lineItems'][] = $this->formatLineItem(
                $itemAmountCurrency, $orderItem, $product, $numberOfItems
            );
        }

        // Discount cost
        if ($discountAmount != 0) {
            $formFields['lineItems'][] = $this->formatInvoiceDiscount(
                $discountAmount,
                $invoice->getShippingAddress()->getShippingDiscountAmount(),
                $currency
            );
        }

        if ($invoice->getShippingAmount() > 0 || $invoice->getShippingTaxAmount() > 0) {
            $adyenInvoiceShippingAmount = $this->chargedCurrency->getInvoiceShippingAmountCurrency($invoice);
            $formFields['lineItems'][] = $this->formatShippingLineItem(
                $adyenInvoiceShippingAmount,
                $order->getShippingDescription()
            );
        }

        return $formFields;
    }

    public function getOpenInvoiceDataForOrder(Order $order): array
    {
        $formFields = ['lineItems' => []];
        /** @var Quote $cart */
        $cart = $this->cartRepository->get($order->getQuoteId());

        foreach ($cart->getAllVisibleItems() as $item) {
            $itemAmountCurrency = $this->chargedCurrency->getQuoteItemAmountCurrency($item);
            $formFields['lineItems'][] = $this->formatLineItem($itemAmountCurrency, $item);
        }

        if ($cart->getShippingAddress()->getShippingAmount() > 0) {
            $shippingAmountCurrency = $this->chargedCurrency->getQuoteShippingAmountCurrency($cart);
            $formFields['lineItems'][] = $this->formatShippingLineItem(
                $shippingAmountCurrency,
                $order->getShippingDescription()
            );
        }

        return $formFields;
    }

    public function getOpenInvoiceDataForCreditMemo(Payment $payment)
    {
        $formFields = ['lineItems' => []];
        $discountAmount = 0;
        $creditMemo = $payment->getCreditMemo();
        $currency = $this->chargedCurrency->getOrderAmountCurrency($payment->getOrder(), false);

        foreach ($creditMemo->getItems() as $refundItem) {
            $numberOfItems = (int)$refundItem->getQty();
            if ($numberOfItems <= 0) {
                continue;
            }

            $itemAmountCurrency = $this->chargedCurrency->getCreditMemoItemAmountCurrency($refundItem);
            $discountAmount += $itemAmountCurrency->getDiscountAmount();
            $orderItem = $refundItem->getOrderItem();
            $product = $orderItem->getProduct();

            $formFields['lineItems'][] = $this->formatLineItem(
                $itemAmountCurrency, $orderItem, $product, $numberOfItems
            );
        }

        // Discount cost
        if ($discountAmount != 0) {
            $formFields['lineItems'][] = $this->formatInvoiceDiscount(
                $discountAmount,
                $payment->getOrder()->getShippingAddress()->getShippingDiscountAmount(),
                $currency
            );
        }

        // Shipping cost
        $shippingAmountCurrency = $this->chargedCurrency->getCreditMemoShippingAmountCurrency($creditMemo);
        if ($shippingAmountCurrency->getAmount() > 0) {
            $formFields['lineItems'][] = $this->formatShippingLineItem(
                $shippingAmountCurrency, $payment->getOrder()->getShippingDescription()
            );
        }

        return $formFields;
    }

    protected function getImageUrl($item): string
    {
        $product = $item->getProduct();
        $imageUrl = "";

        if ($image = $product->getSmallImage()) {
            $imageUrl = $this->imageHelper->init($product, 'product_page_image_small')
                ->setImageFile($image)
                ->getUrl();
        }

        return $imageUrl;
    }

    protected function formatShippingLineItem(
        AdyenAmountCurrency $shippingAmount,
        string $shippingDescription
    ): array {
        $currency = $shippingAmount->getCurrencyCode();

        $formattedPriceExcludingTax = $this->adyenHelper->formatAmount(
            $shippingAmount->getAmountWithDiscount(),
            $currency
        );
        $formattedPriceIncludingTax = $this->adyenHelper->formatAmount(
            $shippingAmount->getAmountIncludingTaxWithDiscount(),
            $currency
        );

        $formattedTaxAmount = $this->adyenHelper->formatAmount($shippingAmount->getTaxAmount(), $currency);
        $formattedTaxPercentage = $this->adyenHelper->formatAmount(
            $shippingAmount->getCalculatedTaxPercentage(),
            $currency
        );

        return [
            'id' => 'shippingCost',
            'amountExcludingTax' => $formattedPriceExcludingTax,
            'amountIncludingTax' => $formattedPriceIncludingTax,
            'taxAmount' => $formattedTaxAmount,
            'description' => $shippingDescription,
            'quantity' => 1,
            'taxPercentage' => $formattedTaxPercentage
        ];
    }

    protected function formatLineItem(AdyenAmountCurrency $itemAmountCurrency, $item): array
    {
        $currency = $itemAmountCurrency->getCurrencyCode();

        $formattedPriceExcludingTax = $this->adyenHelper->formatAmount(
            $itemAmountCurrency->getAmountWithDiscount(),
            $currency
        );
        $formattedPriceIncludingTax = $this->adyenHelper->formatAmount(
            $itemAmountCurrency->getAmountIncludingTaxWithDiscount(),
            $currency
        );

        $formattedTaxAmount = $this->adyenHelper->formatAmount($itemAmountCurrency->getTaxAmount(), $currency);
        $formattedTaxPercentage = $this->adyenHelper->formatAmount($item->getTaxPercent(), $currency);

        $product = $item->getProduct();

        return [
            'id' => $product->getId(),
            'amountExcludingTax' => $formattedPriceExcludingTax,
            'amountIncludingTax' => $formattedPriceIncludingTax,
            'taxAmount' => $formattedTaxAmount,
            'description' => $item->getName(),
            'quantity' => (int) $item->getQty(),
            'taxPercentage' => $formattedTaxPercentage,
            'productUrl' => $product->getUrlModel()->getUrl($product),
            'imageUrl' => $this->getImageUrl($item)
        ];
    }

    protected function formatInvoiceDiscount(
        mixed $discountAmount, $shippingDiscountAmount, AdyenAmountCurrency $itemAmountCurrency
    ): array
    {
        $description = __('Discount');
        $itemAmount = -$this->adyenHelper->formatAmount(
            $discountAmount + $shippingDiscountAmount, $itemAmountCurrency->getCurrencyCode()
        );

        return [
            'id' => 'Discount',
            'amountExcludingTax' => $itemAmount,
            'amountIncludingTax' => $itemAmount,
            'taxAmount' => 0,
            'description' => $description,
            'quantity' => 1,
            'taxPercentage' => 0
        ];
    }
}

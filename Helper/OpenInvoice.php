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

    public function getOpenInvoiceDataForInvoice(Invoice $invoice): array
    {
        $formFields = ['lineItems' => []];

        /* @var Item $invoiceItem */
        foreach ($invoice->getItems() as $invoiceItem) {
            $numberOfItems = (int)$invoiceItem->getQty();
            $orderItem = $invoiceItem->getOrderItem();

            if ($orderItem->getParentItem() || $numberOfItems <= 0) {
                continue;
            }

            $itemAmountCurrency = $this->chargedCurrency->getInvoiceItemAmountCurrency($invoiceItem);
            $formFields['lineItems'][] = $this->formatLineItem($itemAmountCurrency, $orderItem, $numberOfItems);
        }

        if ($invoice->getShippingAmount() > 0 || $invoice->getShippingTaxAmount() > 0) {
            $adyenInvoiceShippingAmount = $this->chargedCurrency->getInvoiceShippingAmountCurrency($invoice);
            $formFields['lineItems'][] = $this->formatShippingLineItem(
                $adyenInvoiceShippingAmount,
                $invoice->getOrder()->getShippingDescription()
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

    public function getOpenInvoiceDataForCreditMemo(Order\Creditmemo $creditMemo)
    {
        $formFields = ['lineItems' => []];

        foreach ($creditMemo->getItems() as $creditmemoItem) {
            // Child items only identifies the variant data and doesn't contain line item information.
            $isChildItem = $creditmemoItem->getOrderItem()->getParentItem() !== null;
            if ($creditmemoItem->getQty() <= 0 || $isChildItem) {
                continue;
            }

            $itemAmountCurrency = $this->chargedCurrency->getCreditMemoItemAmountCurrency($creditmemoItem);
            $formFields['lineItems'][] = $this->formatLineItem(
                $itemAmountCurrency,
                $creditmemoItem->getOrderItem(),
                $creditmemoItem->getQty()
            );
        }

        if ($creditMemo->getShippingAmount() > 0) {
            $shippingAmountCurrency = $this->chargedCurrency->getCreditMemoShippingAmountCurrency($creditMemo);
            $formFields['lineItems'][] = $this->formatShippingLineItem(
                $shippingAmountCurrency,
                $creditMemo->getOrder()->getShippingDescription()
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

    protected function formatLineItem(AdyenAmountCurrency $itemAmountCurrency, $item, $qty = null): array
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
            'quantity' => (int) ($qty ?? $item->getQty()),
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

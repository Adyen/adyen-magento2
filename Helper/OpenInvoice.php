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

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Exception;
use Magento\Catalog\Helper\Image;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;

class OpenInvoice
{
    const ITEM_CATEGORY_DIGITAL_GOODS = 'DIGITAL_GOODS';
    const ITEM_CATEGORY_PHYSICAL_GOODS = 'PHYSICAL_GOODS';

    protected Data $adyenHelper;
    protected CartRepositoryInterface $cartRepository;
    protected ChargedCurrency $chargedCurrency;
    protected Config $configHelper;
    protected Image $imageHelper;
    protected AdyenLogger $adyenLogger;

    public function __construct(
        Data $adyenHelper,
        CartRepositoryInterface $cartRepository,
        ChargedCurrency $chargedCurrency,
        Config $configHelper,
        Image $imageHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->cartRepository = $cartRepository;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
        $this->imageHelper = $imageHelper;
        $this->adyenLogger = $adyenLogger;
    }

    public function getOpenInvoiceDataForInvoice(Invoice $invoice): array
    {
        $formFields = ['lineItems' => []];

        /* @var InvoiceItem $invoiceItem */
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

    public function getOpenInvoiceDataForCreditMemo(Creditmemo $creditMemo): array
    {
        $formFields = ['lineItems' => []];

        /* @var CreditmemoItem $creditmemoItem */
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

        if ($product && $image = $product->getSmallImage()) {
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

        $formattedPriceIncludingTax = $this->adyenHelper->formatAmount(
            $shippingAmount->getAmountIncludingTax(),
            $currency
        );

        $formattedTaxAmount = $this->adyenHelper->formatAmount($shippingAmount->getTaxAmount(), $currency);

        return [
            'id' => 'shippingCost',
            'amountIncludingTax' => $formattedPriceIncludingTax,
            'taxAmount' => $formattedTaxAmount,
            'description' => $shippingDescription,
            'quantity' => 1
        ];
    }

    protected function formatLineItem(AdyenAmountCurrency $itemAmountCurrency, $item, $qty = null): array
    {
        $currency = $itemAmountCurrency->getCurrencyCode();

        $formattedPriceIncludingTax = $this->adyenHelper->formatAmount(
            $itemAmountCurrency->getAmountIncludingTax(),
            $currency
        );

        $formattedTaxAmount = $this->adyenHelper->formatAmount($itemAmountCurrency->getTaxAmount(), $currency);
        $formattedTaxPercentage = $this->adyenHelper->formatAmount($item->getTaxPercent(), $currency);

        $product = $item->getProduct();

        $lineItem = [
            'id' => $product ? $product->getId() : $item->getProductId(),
            'amountIncludingTax' => $formattedPriceIncludingTax,
            'amountExcludingTax' => $formattedPriceIncludingTax - $formattedTaxAmount,
            'taxAmount' => $formattedTaxAmount,
            'taxPercentage' => $formattedTaxPercentage,
            'description' => $item->getName(),
            'sku' => $item->getSku(),
            'quantity' => (int) ($qty ?? $item->getQty()),
            'productUrl' => $product ? $product->getUrlModel()->getUrl($product) : '',
            'imageUrl' => $this->getImageUrl($item)
        ];

        if ($itemCategory = $this->buildItemCategory($item)) {
            $lineItem['itemCategory'] = $itemCategory;
        }

        return $lineItem;
    }

    /**
     * Builds the `itemCategory` field required for PayPal
     *
     * @param QuoteItem|OrderItem $item
     * @return string|null
     */
    private function buildItemCategory($item): ?string
    {
        try {
            if ($item instanceof QuoteItem) {
                $paymentMethod = $item->getQuote()->getPayment()->getMethod();
            } elseif ($item instanceof OrderItem) {
                $paymentMethod = $item->getOrder()->getPayment()->getMethod();
            }

            if (isset($paymentMethod) && strcmp($paymentMethod, PaymentMethods::ADYEN_PAYPAL) === 0) {
                $isVirtual = boolval($item->getIsVirtual());
                $category = $isVirtual ? self::ITEM_CATEGORY_DIGITAL_GOODS : self::ITEM_CATEGORY_PHYSICAL_GOODS;
            }
        } catch (Exception $e) {
            $this->adyenLogger->error(
                __('An error occurred while fetching the `itemCategory`: %1', $e->getMessage())
            );
        }

        return $category ?? null;
    }

    /**
     * @deprecated
     */
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
            'amountIncludingTax' => $itemAmount,
            'taxAmount' => 0,
            'description' => $description,
            'quantity' => 1,
            'taxPercentage' => 0
        ];
    }
}

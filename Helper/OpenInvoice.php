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
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Invoice\Item;

class OpenInvoice
{
    /**
     * @var AbstractHelper
     */
    protected $adyenHelper;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var ChargedCurrency
     */
    protected $chargedCurrency;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var Image
     */
    protected $imageHelper;

    public function __construct(
        Data                    $adyenHelper,
        CartRepositoryInterface $cartRepository,
        ChargedCurrency         $chargedCurrency,
        Config                  $configHelper,
        Image                   $imageHelper
    )
    {
        $this->adyenHelper = $adyenHelper;
        $this->cartRepository = $cartRepository;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
        $this->imageHelper = $imageHelper;
    }

    public function getOpenInvoiceDataForLastInvoice(Payment $payment): array
    {
        $formFields = [];
        $order = $payment->getOrder();
        $invoices = $order->getInvoiceCollection();

        $currency = $this->chargedCurrency
            ->getOrderAmountCurrency($payment->getOrder(), false)
            ->getCurrencyCode();

        // The latest invoice will contain only the selected items(and quantities) for the (partial) capture
        /** @var Invoice $invoice */
        $invoice = $invoice ?: $invoices->getLastItem();
        $discountAmount = 0;

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
            $formFields['lineItems'][] = $this->formatInoviceItem($itemAmountCurrency, $orderItem, $currency, $product, $numberOfItems);
        }

        // Discount cost
        if ($discountAmount != 0) {
            $formFields['lineItems'][] = $this->formatInvoiceDiscount(
                $discountAmount,
                $invoice->getShippingAddress()->getShippingDiscountAmount(),
                $itemAmountCurrency
            );
        }

        if ($invoice->getShippingAmount() > 0 || $invoice->getShippingTaxAmount() > 0) {
            $adyenInvoiceShippingAmount = $this->chargedCurrency->getInvoiceShippingAmountCurrency($invoice);
            $formFields['lineItems'][] = $this->formatInvoiceShippingItem($adyenInvoiceShippingAmount, $order->getShippingDescription());
        }

        return $formFields;
    }

    public function getOpenInvoiceDataForOrder(Order $order): array
    {
        $formFields = [
            'lineItems' => []
        ];

        /** @var \Magento\Quote\Model\Quote $cart */
        $cart = $this->cartRepository->get($order->getQuoteId());
        $amountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order);
        $currency = $amountCurrency->getCurrencyCode();
        $discountAmount = 0;

        foreach ($cart->getAllVisibleItems() as $item) {
            $itemAmountCurrency = $this->chargedCurrency->getQuoteItemAmountCurrency($item);
            $numberOfItems = (int)$item->getQty();
            $product = $item->getProduct();
            // Summarize the discount amount item by item
            $discountAmount += $itemAmountCurrency->getDiscountAmount();
            $formFields['lineItems'][] = $this->formatInoviceItem($itemAmountCurrency, $item, $currency, $product, $numberOfItems);
        }

        // Discount cost
        if ($discountAmount != 0) {
            $formFields['lineItems'][] = $this->formatInvoiceDiscount(
                $discountAmount,
                $cart->getShippingAddress()->getShippingDiscountAmount(),
                $itemAmountCurrency
            );
        }

        // Shipping cost
        if ($cart->getShippingAddress()->getShippingAmount() > 0 || $cart->getShippingAddress()->getShippingTaxAmount() > 0) {
            $shippingAmountCurrency = $this->chargedCurrency->getQuoteShippingAmountCurrency($cart);
            $formFields['lineItems'][] = $this->formatInvoiceShippingItem($shippingAmountCurrency, $order->getShippingDescription());
        }

        return $formFields;
    }

    public function getOpenInvoiceDataForCreditMemo(Payment $payment)
    {
        $formFields = [];
        $count = 0;

        // Construct AdyenAmountCurrency from creditmemo
        /**
         * @var Creditmemo $creditMemo
         */
        $creditMemo = $payment->getCreditMemo();

        foreach ($creditMemo->getItems() as $refundItem) {
            $numberOfItems = (int)$refundItem->getQty();
            if ($numberOfItems == 0) {
                continue;
            }

            ++$count;
            $itemAmountCurrency = $this->chargedCurrency->getCreditMemoItemAmountCurrency($refundItem);

            $formFields = $this->adyenHelper->createOpenInvoiceLineItem(
                $formFields,
                $count,
                $refundItem->getName(),
                $itemAmountCurrency->getAmount(),
                $itemAmountCurrency->getCurrencyCode(),
                $itemAmountCurrency->getTaxAmount(),
                $itemAmountCurrency->getAmount() + $itemAmountCurrency->getTaxAmount(),
                $refundItem->getOrderItem()->getTaxPercent(),
                $numberOfItems,
                $payment,
                $refundItem->getId()
            );
        }

        // Shipping cost
        $shippingAmountCurrency = $this->chargedCurrency->getCreditMemoShippingAmountCurrency($creditMemo);
        if ($shippingAmountCurrency->getAmount() > 0) {
            ++$count;
            $formFields = $this->adyenHelper->createOpenInvoiceLineShipping(
                $formFields,
                $count,
                $payment->getOrder(),
                $shippingAmountCurrency->getAmount(),
                $shippingAmountCurrency->getTaxAmount(),
                $shippingAmountCurrency->getCurrencyCode(),
                $payment
            );
        }

        // Adjustment
        $adjustmentAmountCurrency = $this->chargedCurrency->getCreditMemoAdjustmentAmountCurrency($creditMemo);
        if ($adjustmentAmountCurrency->getAmount() != 0) {
            $positive = $adjustmentAmountCurrency->getAmount() > 0 ? 'Positive' : '';
            $negative = $adjustmentAmountCurrency->getAmount() < 0 ? 'Negative' : '';
            $description = "Adjustment - " . implode(' | ', array_filter([$positive, $negative]));

            ++$count;
            $formFields = $this->adyenHelper->createOpenInvoiceLineAdjustment(
                $formFields,
                $count,
                $description,
                $adjustmentAmountCurrency->getAmount(),
                $adjustmentAmountCurrency->getCurrencyCode(),
                $payment
            );
        }

        $formFields['openinvoicedata.numberOfLines'] = $count;

        //Retrieve acquirerReference from the adyen_invoice
        $invoiceId = $creditMemo->getInvoice()->getId();
        $invoices = $this->adyenInvoiceCollectionFactory->create()
            ->addFieldToFilter('invoice_id', $invoiceId);

        $invoice = $invoices->getFirstItem();

        if ($invoice) {
            $formFields['acquirerReference'] = $invoice->getAcquirerReference();
        }

        return $formFields;
    }

    /**
     * @param string $item
     * @return string
     */
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

    protected function formatInvoiceShippingItem(AdyenAmountCurrency $shippingAmount, string $shippingDescription): array
    {
        $currency = $shippingAmount->getCurrencyCode();
        $formattedPriceExcludingTax = $this->adyenHelper->formatAmount($shippingAmount->getAmount(), $currency);
        $formattedPriceIncludingTax = $this->adyenHelper->formatAmount($shippingAmount->getAmountIncludingTax(), $currency);
        $formattedTaxAmount = $this->adyenHelper->formatAmount($shippingAmount->getTaxAmount(), $currency);

        return [
            'id' => 'shippingCost',
            'amountExcludingTax' => $formattedPriceExcludingTax,
            'amountIncludingTax' => $formattedPriceIncludingTax,
            'taxAmount' => $formattedTaxAmount,
            'description' => $shippingDescription,
            'quantity' => 1,
            'taxPercentage' => (int)round(($formattedTaxAmount / $formattedPriceExcludingTax) * 100 * 100)
        ];
    }

    public function formatInoviceItem(AdyenAmountCurrency $itemAmountCurrency, $item, $currency, $product, int $numberOfItems): array
    {
        $formattedPriceExcludingTax = $this->adyenHelper->formatAmount(
            $itemAmountCurrency->getAmount(),
            $itemAmountCurrency->getCurrencyCode()
        );

        $formattedPriceIncludingTax = $this->adyenHelper->formatAmount(
            $itemAmountCurrency->getAmountIncludingTax(),
            $itemAmountCurrency->getCurrencyCode()
        );

        $formattedTaxAmount = $this->adyenHelper->formatAmount(
            $itemAmountCurrency->getTaxAmount(),
            $itemAmountCurrency->getCurrencyCode()
        );

        $formattedTaxPercentage = $this->adyenHelper->formatAmount($item->getTaxPercent(), $currency);

        $output = [
            'id' => $product->getId(),
            'amountExcludingTax' => $formattedPriceExcludingTax,
            'amountIncludingTax' => $formattedPriceIncludingTax,
            'taxAmount' => $formattedTaxAmount,
            'description' => $item->getName(),
            'quantity' => $numberOfItems,
            'taxPercentage' => $formattedTaxPercentage,
            'productUrl' => $product->getUrlModel()->getUrl($product),
            'imageUrl' => $this->getImageUrl($item)
        ];
        return $output;
    }

    public function formatInvoiceDiscount(mixed $discountAmount, $shippingDiscountAmount, AdyenAmountCurrency $itemAmountCurrency): array
    {
        $description = __('Discount');
        $itemAmount = -$this->adyenHelper->formatAmount($discountAmount + $shippingDiscountAmount, $itemAmountCurrency->getCurrencyCode());

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

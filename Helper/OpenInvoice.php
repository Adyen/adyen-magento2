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

use JetBrains\PhpStorm\ArrayShape;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Sales\Model\Order;

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
    protected  $configHelper;

    /**
     * @var Image
     */
    protected $imageHelper;

    public function __construct(
        Data $adyenHelper,
        CartRepositoryInterface $cartRepository,
        ChargedCurrency $chargedCurrency,
        Config $configHelper,
        Image $imageHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->cartRepository = $cartRepository;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
        $this->imageHelper = $imageHelper;
    }

    public function getOpenInvoiceDataFromPayment($payment): array
    {
        $formFields = [];
        $count = 0;
        $order = $payment->getOrder();
        $invoices = $order->getInvoiceCollection();

        $currency = $this->chargedCurrency
            ->getOrderAmountCurrency($payment->getOrder(), false)
            ->getCurrencyCode();

        // The latest invoice will contain only the selected items(and quantities) for the (partial) capture
        $latestInvoice = $invoices->getLastItem();

        /* @var \Magento\Sales\Model\Order\Invoice\Item $invoiceItem */
        foreach ($latestInvoice->getItems() as $invoiceItem) {
            $numberOfItems = (int)$invoiceItem->getQty();

            if ($invoiceItem->getOrderItem()->getParentItem() || $numberOfItems <= 0) {
                continue;
            }

            ++$count;
            $itemAmountCurrency = $this->chargedCurrency->getInvoiceItemAmountCurrency($invoiceItem);

            $formFields = $this->adyenHelper->createOpenInvoiceLineItem(
                $formFields,
                $count,
                $invoiceItem->getName(),
                $itemAmountCurrency->getAmount(),
                $currency,
                $itemAmountCurrency->getTaxAmount(),
                $itemAmountCurrency->getAmount() + $itemAmountCurrency->getTaxAmount(),
                $invoiceItem->getOrderItem()->getTaxPercent(),
                $numberOfItems,
                $payment,
                $invoiceItem->getId()
            );
        }

        // Shipping cost
        if ($latestInvoice->getShippingAmount() > 0) {
            ++$count;
            $adyenInvoiceShippingAmount = $this->chargedCurrency->getInvoiceShippingAmountCurrency($latestInvoice);
            $formFields = $this->adyenHelper->createOpenInvoiceLineShipping(
                $formFields,
                $count,
                $order,
                $adyenInvoiceShippingAmount->getAmount(),
                $adyenInvoiceShippingAmount->getTaxAmount(),
                $adyenInvoiceShippingAmount->getCurrencyCode(),
                $payment
            );
        }

        $formFields['openinvoicedata.numberOfLines'] = $count;

        return $formFields;
    }


    public function getOpenInvoiceDataFromOrder(Order $order): array
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
            $numberOfItems = (int)$item->getQty();

            $itemAmountCurrency = $this->chargedCurrency->getQuoteItemAmountCurrency($item);

            // Summarize the discount amount item by item
            $discountAmount += $itemAmountCurrency->getDiscountAmount();

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

            $formFields['lineItems'][] = [
                'id' => $item->getProduct()->getId(),
                'amountExcludingTax' => $formattedPriceExcludingTax,
                'amountIncludingTax' => $formattedPriceIncludingTax,
                'taxAmount' => $formattedTaxAmount,
                'description' => $item->getName(),
                'quantity' => $numberOfItems,
                'taxPercentage' => $formattedTaxPercentage,
                'productUrl' => $item->getProduct()->getUrlModel()->getUrl($item->getProduct()),
                'imageUrl' => $this->getImageUrl($item)
            ];
        }

        // Discount cost
        if ($discountAmount != 0) {
            $description = __('Discount');
            $itemAmount = -$this->adyenHelper->formatAmount(
                $discountAmount + $cart->getShippingAddress()->getShippingDiscountAmount(),
                $itemAmountCurrency->getCurrencyCode()
            );
            $itemVatAmount = "0";
            $itemVatPercentage = "0";
            $numberOfItems = 1;

            $formFields['lineItems'][] = [
                'id' => 'Discount',
                'amountExcludingTax' => $itemAmount,
                'amountIncludingTax' => $itemAmount,
                'taxAmount' => $itemVatAmount,
                'description' => $description,
                'quantity' => $numberOfItems,
                'taxPercentage' => $itemVatPercentage
            ];
        }

        // Shipping cost
        if ($cart->getShippingAddress()->getShippingAmount() > 0 ||
            $cart->getShippingAddress()->getShippingTaxAmount() > 0
        ) {
            $shippingAmountCurrency = $this->chargedCurrency->getQuoteShippingAmountCurrency($cart);

            $formattedPriceExcludingTax = $this->adyenHelper->formatAmount(
                $shippingAmountCurrency->getAmount(),
                $currency
            );


            $formattedPriceIncludingTax = $this->adyenHelper->formatAmount(
                $shippingAmountCurrency->getAmountIncludingTax(),
                $currency
            );


            $formattedTaxAmount = $this->adyenHelper->formatAmount(
                $shippingAmountCurrency->getTaxAmount(),
                $currency
            );


            $formFields['lineItems'][] = [
                'id' => 'shippingCost',
                'amountExcludingTax' => $formattedPriceExcludingTax,
                'amountIncludingTax' => $formattedPriceIncludingTax,
                'taxAmount' => $formattedTaxAmount,
                'description' => $order->getShippingDescription(),
                'quantity' => 1,
                'taxPercentage' => (int) round(($formattedTaxAmount / $formattedPriceExcludingTax) * 100 * 100)
            ];
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
}

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

use Magento\Catalog\Helper\Image;
use Magento\Sales\Model\Order as MagentoOrder;

class OpenInvoice
{
    /**
     * @var AbstractHelper
     */
    protected $adyenHelper;

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
        ChargedCurrency $chargedCurrency,
        Config $configHelper,
        Image $imageHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->chargedCurrency = $chargedCurrency;
        $this->configHelper = $configHelper;
        $this->imageHelper = $imageHelper;
    }

    public function getOpenInvoiceData(MagentoOrder $order): array
    {
        $formFields = [
            'lineItems' => []
        ];

        $amountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order);
        $currency = $amountCurrency->getCurrencyCode();
        $discountAmount = 0;

        /** @var MagentoOrder\Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $item->setOrder($order);
            $numberOfItems = (int)$item->getQtyOrdered();

            $itemAmountCurrency = $this->chargedCurrency->getOrderItemAmountCurrency($item);

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
                'id' => (string) $item->getProductId(),
                'amountExcludingTax' => $formattedPriceExcludingTax,
                'amountIncludingTax' => $formattedPriceIncludingTax,
                'taxAmount' => $formattedTaxAmount,
                'description' => $item->getName(),
                'quantity' => $numberOfItems,
                'taxPercentage' => $formattedTaxPercentage,
                'productUrl' => $this->getProductUrl($item),
                'imageUrl' => $this->getImageUrl($item)
            ];
        }

        // Discount cost
        if ($discountAmount != 0) {
            $description = __('Discount');
            $itemAmount = -$this->adyenHelper->formatAmount(
                $discountAmount + $order->getShippingDiscountAmount(),
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
        if ($order->getShippingAmount() > 0 ||
            $order->getShippingTaxAmount() > 0
        ) {
            $shippingAmountCurrency = $this->chargedCurrency->getOrderShippingAmountCurrency($order);

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

    protected function getImageUrl(MagentoOrder\Item $item): string
    {
        $imageUrl = "";
        $product = $item->getProduct();
        if ($product === null) {
            return $imageUrl;
        }

        if ($image = $product->getSmallImage()) {
            $imageUrl = $this->imageHelper->init($product, 'product_page_image_small')
                ->setImageFile($image)
                ->getUrl();
        }

        return $imageUrl;
    }

    protected function getProductUrl(MagentoOrder\Item $item): string
    {
        $product = $item->getProduct();
        if ($product === null) {
            return '';
        }

        return $product->getUrlModel()->getUrl($item->getProduct());
    }
}

<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class AdditionalDataLevel23DataBuilder implements BuilderInterface
{
    const ENHANCED_SCHEME_DATA_PREFIX = 'enhancedSchemeData';
    const ITEM_DETAIL_LINE_PREFIX = 'itemDetailLine';
    const UNIT_OF_MEASURE_PCS = 'pcs';

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param ChargedCurrency $chargedCurrency
     * @param Requests $adyenRequestHelper
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        public Config $config,
        public StoreManagerInterface $storeManager,
        public ChargedCurrency $chargedCurrency,
        public Requests $adyenRequestHelper,
        public Data $adyenHelper,
        public AdyenLogger $adyenLogger
    )
    { }

    /**
     * This data builder creates `additionalData` object for Level 2/3 enhanced scheme data.
     * For more information refer to https://docs.adyen.com/payment-methods/cards/enhanced-scheme-data/l2-l3
     *
     * @param array $buildSubject
     * @return array|array[]
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        $additionalDataLevel23 = [];

        if ($this->config->sendLevel23AdditionalData($this->storeManager->getStore()->getId())) {
            $paymentDataObject = SubjectReader::readPayment($buildSubject);
            $payment = $paymentDataObject->getPayment();

            /** @var Order $order */
            $order = $payment->getOrder();
            $currencyCode = $this->chargedCurrency->getOrderAmountCurrency($order)->getCurrencyCode();

            // `totalTaxAmount` field is required and L2/L3 data can not be generated without this field.
            if (empty($order->getTaxAmount()) || $order->getTaxAmount() < 0 || $order->getTaxAmount() === 0) {
                $this->adyenLogger->warning(__('L2/L3 data can not be generated if tax amount is zero.'));
                return [];
            }

            $additionalDataLevel23 = [
                self::ENHANCED_SCHEME_DATA_PREFIX . '.orderDate' => date('dmy', time()),
                self::ENHANCED_SCHEME_DATA_PREFIX . '.customerReference' =>
                    $this->adyenRequestHelper->getShopperReference($order->getCustomerId(), $order->getIncrementId()),
                self::ENHANCED_SCHEME_DATA_PREFIX . '.totalTaxAmount' =>
                    (string) $this->adyenHelper->formatAmount($order->getTaxAmount(), $currencyCode)
            ];

            if ($order->getIsNotVirtual()) {
                $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.freightAmount'] =
                    (string) $this->adyenHelper->formatAmount($order->getBaseShippingAmount(), $currencyCode);

                $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.destinationPostalCode'] =
                    $order->getShippingAddress()->getPostcode();

                $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.destinationCountryCode'] =
                    $order->getShippingAddress()->getCountryId();

                if (!empty($order->getShippingAddress()->getRegionCode())) {
                    $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.destinationStateProvinceCode'] =
                        $order->getShippingAddress()->getRegionCode();
                }
            }

            $itemIndex = 1;
            foreach ($order->getItems() as $item) {
                if (!$this->validateLineItem($item)) {
                    continue;
                }

                $itemPrefix = self::ENHANCED_SCHEME_DATA_PREFIX . '.' . self::ITEM_DETAIL_LINE_PREFIX;

                $additionalDataLevel23[$itemPrefix . $itemIndex . '.description'] = $item->getName();
                $additionalDataLevel23[$itemPrefix . $itemIndex . '.discountAmount'] =
                    (string) $this->adyenHelper->formatAmount($item->getDiscountAmount(), $currencyCode);
                $additionalDataLevel23[$itemPrefix . $itemIndex . '.commodityCode'] = (string) $item->getQuoteItemId();
                $additionalDataLevel23[$itemPrefix . $itemIndex . '.productCode'] = $item->getSku();
                $additionalDataLevel23[$itemPrefix . $itemIndex . '.unitOfMeasure'] = self::UNIT_OF_MEASURE_PCS;
                $additionalDataLevel23[$itemPrefix . $itemIndex . '.quantity'] = (string) $item->getQtyOrdered();
                $additionalDataLevel23[$itemPrefix . $itemIndex . '.unitPrice'] =
                    (string) $this->adyenHelper->formatAmount($item->getPrice(), $currencyCode);
                $additionalDataLevel23[$itemPrefix . $itemIndex . '.totalAmount'] =
                    (string) $this->adyenHelper->formatAmount($item->getRowTotal(), $currencyCode);

                $itemIndex++;
            }
        }

        return [
            'body' => [
                'additionalData' => $additionalDataLevel23
            ]
        ];
    }

    /**
     * Required fields `unitPrice`, `totalAmount` or `quantity` can not be null or zero in the line items.
     *
     * @param OrderItemInterface $orderItem
     * @return bool
     */
    private function validateLineItem(OrderItemInterface $orderItem): bool
    {
        $validationResult = true;

        // Products variants get added to the order as separate items, filter out the variants.
        if ($orderItem->getPrice() === 0 && !empty($orderItem->getParentItem())) {
            $validationResult = false;
        }

        // `unitPrice` should be a non-zero numeric value.
        if ($orderItem->getPrice() === 0) {
            $validationResult = false;
        }

        // `totalAmount` should be a non-zero numeric value.
        if ($orderItem->getRowTotal() === 0) {
            $validationResult = false;
        }

        // `quantity` should be a positive integer. If not, skip the line item.
        if ($orderItem->getQtyOrdered() < 1) {
            $validationResult = false;
        }

        return $validationResult;
    }
}

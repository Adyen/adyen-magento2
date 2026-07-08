<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
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
     * @param Level23DataValidator $level23DataValidator
     */
    public function __construct(
        public Config $config,
        public StoreManagerInterface $storeManager,
        public ChargedCurrency $chargedCurrency,
        public Requests $adyenRequestHelper,
        public Data $adyenHelper,
        public AdyenLogger $adyenLogger,
        public Level23DataValidator $level23DataValidator
    ) { }

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
        $request = [];

        if ($this->config->sendLevel23AdditionalData($this->storeManager->getStore()->getId())) {
            $paymentDataObject = SubjectReader::readPayment($buildSubject);
            $payment = $paymentDataObject->getPayment();

            /** @var Order $order */
            $order = $payment->getOrder();
            $currencyCode = $this->chargedCurrency->getOrderAmountCurrency($order)->getCurrencyCode();

            $additionalDataLevel23 = [];

            $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.orderDate'] = date('dmy', time());

            $customerReference = $this->level23DataValidator->sanitizeCustomerReference(
                (string) $this->adyenRequestHelper->getShopperReference(
                    $order->getCustomerId(),
                    $order->getIncrementId()
                )
            );
            if ($customerReference !== null) {
                $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.customerReference'] =
                    $customerReference;
            }

            $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.totalTaxAmount'] =
                (string) $this->adyenHelper->formatAmount($order->getTaxAmount(), $currencyCode);

            if ($order->getIsNotVirtual()) {
                $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.freightAmount'] =
                    (string) $this->adyenHelper->formatAmount($order->getBaseShippingAmount(), $currencyCode);

                $postalCode = $this->level23DataValidator->sanitizePostalCode(
                    (string) $order->getShippingAddress()->getPostcode()
                );
                if ($postalCode !== null) {
                    $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.destinationPostalCode'] =
                        $postalCode;
                }

                $alpha3CountryCode = $this->level23DataValidator->convertCountryCodeToAlpha3(
                    (string) $order->getShippingAddress()->getCountryId()
                );
                if ($alpha3CountryCode !== null) {
                    $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.destinationCountryCode'] =
                        $alpha3CountryCode;
                }

                $regionCode = $order->getShippingAddress()->getRegionCode();
                if (!empty($regionCode)) {
                    $sanitizedRegionCode = $this->level23DataValidator->sanitizeStateProvinceCode(
                        (string) $regionCode
                    );
                    if ($sanitizedRegionCode !== null) {
                        $additionalDataLevel23[self::ENHANCED_SCHEME_DATA_PREFIX . '.destinationStateProvinceCode'] =
                            $sanitizedRegionCode;
                    }
                }
            }

            $itemIndex = 1;
            foreach ($order->getItems() as $item) {
                if (!$this->level23DataValidator->validateLineItemInput(
                    $item->getPrice(),
                    $item->getQtyOrdered()
                )) {
                    continue;
                }

                $lineItemData = $this->buildLineItemData($item, $currencyCode);
                if ($lineItemData === null) {
                    continue;
                }

                $itemPrefix = self::ENHANCED_SCHEME_DATA_PREFIX . '.' . self::ITEM_DETAIL_LINE_PREFIX;
                foreach ($lineItemData as $field => $value) {
                    $additionalDataLevel23[$itemPrefix . $itemIndex . '.' . $field] = $value;
                }

                $itemIndex++;
            }

            $request = [
                'body' => [
                    'additionalData' => $additionalDataLevel23
                ]
            ];
        }

        return $request;
    }

    /**
     * Build and validate a single line item's data.
     * Returns null if any required field fails validation.
     *
     * @param OrderItemInterface $item
     * @param string $currencyCode
     * @return array|null
     */
    private function buildLineItemData(OrderItemInterface $item, string $currencyCode): ?array
    {
        $description = $this->level23DataValidator->sanitizeDescription((string) $item->getName());
        if ($description === null) {
            $this->adyenLogger->addAdyenInfoLog(
                sprintf('L2/L3: Skipping line item, description validation failed for item "%s"', $item->getName())
            );
            return null;
        }

        $productCode = $this->level23DataValidator->sanitizeProductCode((string) $item->getSku());
        if ($productCode === null) {
            $this->adyenLogger->addAdyenInfoLog(
                sprintf('L2/L3: Skipping line item, productCode validation failed for SKU "%s"', $item->getSku())
            );
            return null;
        }

        $commodityCode = $this->level23DataValidator->sanitizeCommodityCode((string) $item->getQuoteItemId());
        if ($commodityCode === null) {
            $this->adyenLogger->addAdyenInfoLog('L2/L3: Skipping line item, commodityCode validation failed.');
            return null;
        }

        $quantity = (int) $item->getQtyOrdered();
        $unitPrice = (int) $this->adyenHelper->formatAmount($item->getPrice(), $currencyCode);
        $discountAmount = (int) $this->adyenHelper->formatAmount($item->getDiscountAmount(), $currencyCode);

        $totalAmount = $this->level23DataValidator->calculateLineItemTotalAmount(
            $quantity,
            $unitPrice,
            $discountAmount
        );

        if ($totalAmount <= 0) {
            $this->adyenLogger->addAdyenInfoLog(
                sprintf(
                    'L2/L3: Skipping line item "%s", calculated totalAmount is %d (qty=%d, unitPrice=%d, discount=%d)',
                    $item->getSku(),
                    $totalAmount,
                    $quantity,
                    $unitPrice,
                    $discountAmount
                )
            );
            return null;
        }

        $formattedTotalAmount = (string) $totalAmount;
        if (!$this->level23DataValidator->isAmountNotAllZeros($formattedTotalAmount)) {
            return null;
        }

        return [
            'description' => $description,
            'discountAmount' => (string) $discountAmount,
            'commodityCode' => $commodityCode,
            'productCode' => $productCode,
            'unitOfMeasure' => self::UNIT_OF_MEASURE_PCS,
            'quantity' => (string) $quantity,
            'unitPrice' => (string) $unitPrice,
            'totalAmount' => $formattedTotalAmount
        ];
    }
}

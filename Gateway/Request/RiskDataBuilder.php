<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\StoreManagerInterface;

class RiskDataBuilder implements BuilderInterface
{
    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * PaymentDataBuilder constructor.
     *
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param ChargedCurrency $chargedCurrency
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        ChargedCurrency $chargedCurrency
    ) {
        $this->chargedCurrency = $chargedCurrency;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        $requestBody = [];
        if ($this->config->sendAdditionalRiskData($this->storeManager->getStore()->getId())) {
            $paymentDataObject = SubjectReader::readPayment($buildSubject);
            $payment = $paymentDataObject->getPayment();
            $order = $payment->getOrder();
            $currencyCode = $this->chargedCurrency->getOrderAmountCurrency($order)->getCurrencyCode();
            $additionalData = [];
            $basketPrefix = 'riskdata.basket.item';
            $promotionsPrefix = 'riskdata.promotions.promotion0';

            $itemIndex = 0;
            foreach ($order->getItems() as $item) {
                /** @var Item $item */
                if ($item->getPrice() == 0 && !empty($item->getParentItem())) {
                    // products with variants get added to the order twice.
                    continue;
                }

                $additionalData[$basketPrefix . $itemIndex . '.amountPerItem'] = $item->getPrice(); // convert to minor units
                $additionalData[$basketPrefix . $itemIndex . '.currency'] = $currencyCode;
                $additionalData[$basketPrefix . $itemIndex . '.itemID'] = $item->getQuoteItemId();
                $additionalData[$basketPrefix . $itemIndex . '.productTitle'] = $item->getName();
                $additionalData[$basketPrefix . $itemIndex . '.quantity'] = $item->getQtyOrdered();
                $additionalData[$basketPrefix . $itemIndex . '.sku'] = $item->getSku();

                $itemIndex++;
            }

            if ($order->getDiscountAmount() !== 0.0) {
                $additionalData[$promotionsPrefix . '.promotionDiscountAmount'] = $order->getDiscountAmount(); // convert to minor units
                $additionalData[$promotionsPrefix . '.promotionCode'] = $order->getCouponCode();
                $additionalData[$promotionsPrefix . '.promotionDiscountCurrency'] = $currencyCode;
                $additionalData[$promotionsPrefix . '.promotionName'] = $order->getDataByKey('coupon_rule_name');
            }

            $requestBody['additionalData'] = $additionalData;
        }

        $requestBody["fraudOffset"] = "0";

        return ['body' => $requestBody];
    }
}

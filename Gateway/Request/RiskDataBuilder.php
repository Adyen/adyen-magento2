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
     */
    public function build(array $buildSubject)
    {
        $requestBody = [];
        if ($this->config->sendAdditionalRiskData($this->storeManager->getStore()->getId())) {
            $paymentDataObject = SubjectReader::readPayment($buildSubject);
            $payment = $paymentDataObject->getPayment();
            $order = $payment->getOrder();
            $amountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order);
            $currencyCode = $amountCurrency->getCurrencyCode();

            $itemIndex = 0;
            foreach ($order->getItems() as $item) {
                /** @var Item $item */
                if ($item->getPrice() == 0 && !empty($item->getParentItem())) {
                    // products with variants get added to the order twice.
                    continue;
                }

                $requestBody['additionalData']['riskdata.basket.item'.$itemIndex.'.amountPerItem'] = $item->getPrice();
                $requestBody['additionalData']['riskdata.basket.item'.$itemIndex.'.currency'] = $currencyCode;
                $requestBody['additionalData']['riskdata.basket.item'.$itemIndex.'.itemID'] = $item->getQuoteItemId();
                $requestBody['additionalData']['riskdata.basket.item'.$itemIndex.'.productTitle'] = $item->getName();
                $requestBody['additionalData']['riskdata.basket.item'.$itemIndex.'.quantity'] = $item->getQtyOrdered();
                $requestBody['additionalData']['riskdata.basket.item'.$itemIndex.'.sku'] = $item->getSku();

                $itemIndex++;
            }

            if ($order->getDiscountAmount() != 0) {
                $requestBody['additionalData']['riskdata.promotions.promotion0.promotionDiscountAmount'] = $order->getDiscountAmount();
                $requestBody['additionalData']['riskdata.promotions.promotion0.promotionCode'] = $order->getCouponCode();
                $requestBody['additionalData']['riskdata.promotions.promotion0.promotionDiscountCurrency'] = $order->getOrderCurrencyCode();
                $requestBody['additionalData']['riskdata.promotions.promotion0.promotionName'] = $order->getDataByKey('coupon_rule_name');
            }
        }

        $requestBody["fraudOffset"] = "0";

        return ['body' => $requestBody];
    }
}

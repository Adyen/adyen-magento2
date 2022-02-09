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
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\StoreManagerInterface;

class AdditionalDataLevel23DataBuilder implements BuilderInterface
{
    /**
     * @var Config
     */
    private $config;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        ChargedCurrency $chargedCurrency
    )
    {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->chargedCurrency = $chargedCurrency;
    }

    public function build(array $buildSubject)
    {
        $requestBody = [];
        if ($this->config->sendAdditionalRiskData($this->storeManager->getStore()->getId())) {
            $paymentDataObject = SubjectReader::readPayment($buildSubject);
            $payment = $paymentDataObject->getPayment();
            $order = $payment->getOrder();
            $amountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order);
            $requestBody['additionalData']['enhancedSchemeData.totalTaxAmount'] = $amountCurrency->getTaxAmount();
            $requestBody['additionalData']['enhancedSchemeData.customerReference'] = str_pad($order->getCustomerId(), 3, '0', STR_PAD_LEFT);
            $requestBody['additionalData']['enhancedSchemeData.freightAmount'] = $order->getBaseShippingAmount();
            $requestBody['additionalData']['enhancedSchemeData.destinationPostalCode'] = $order;
            $requestBody['additionalData']['enhancedSchemeData.destinationCountryCode'] = $order;

            $itemIndex = 0;
            foreach ($order->getItems() as $item) {
                /** @var Item $item */
                if ($item->getPrice() == 0 && !empty($item->getParentItem())) {
                    // products with variants get added to the order twice.
                    continue;
                }

                $requestBody['additionalData']['enhancedSchemeData.itemDetailLine'.$itemIndex.'.description'] = $item->getName();
                $requestBody['additionalData']['enhancedSchemeData.itemDetailLine'.$itemIndex.'.unitPrice'] = $item->getPrice();
                $requestBody['additionalData']['enhancedSchemeData.itemDetailLine'.$itemIndex.'.discountAmount'] = $item->getDiscountAmount();
                $requestBody['additionalData']['enhancedSchemeData.itemDetailLine'.$itemIndex.'.commodityCode'] = $item->getQuoteItemId();
                $requestBody['additionalData']['enhancedSchemeData.itemDetailLine'.$itemIndex.'.quantity'] = $item->getQtyOrdered();
                $requestBody['additionalData']['enhancedSchemeData.itemDetailLine'.$itemIndex.'.productCode'] = $item->getSku();
                $requestBody['additionalData']['enhancedSchemeData.itemDetailLine'.$itemIndex.'.totalAmount'] = $item->getRowTotal();

                $itemIndex++;
            }

        }

        return ['body' => $requestBody];
    }
}

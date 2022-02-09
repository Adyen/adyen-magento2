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

use Adyen\Payment\Helper\Config;
use Adyen\Util\Uuid;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
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

    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager
    )
    {
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    public function build(array $buildSubject)
    {
        $requestBody = [];
        if ($this->config->sendLevel23AdditionalData($this->storeManager->getStore()->getId())) {
            $paymentDataObject = SubjectReader::readPayment($buildSubject);
            $payment = $paymentDataObject->getPayment();
            /** @var Order $order */
            $order = $payment->getOrder();

            if (!$order->getCustomerIsGuest()) {
                $customerReference = str_pad($order->getCustomerId(), 3, '0', STR_PAD_LEFT);
            } else {
                $uuid = Uuid::generateV4();
                $guestCustomerId = $order->getIncrementId() . $uuid;
                $customerReference = $guestCustomerId;
            }
            $prefix = 'enhancedSchemeData';
            $requestBody['additionalData'][$prefix . '.totalTaxAmount'] = $order->getTaxAmount(); // convert to minor units
            $requestBody['additionalData'][$prefix . '.customerReference'] = $customerReference;
            if ($order->getIsNotVirtual()) {
                $requestBody['additionalData'][$prefix . '.freightAmount'] = $order->getBaseShippingAmount(); // convert to minor units
                $requestBody['additionalData'][$prefix . '.destinationPostalCode'] = $order->getShippingAddress()->getPostcode();
                $requestBody['additionalData'][$prefix . '.destinationCountryCode'] = $order->getShippingAddress()->getCountryId();
            }

            $itemIndex = 0;
            foreach ($order->getItems() as $item) {
                /** @var Item $item */
                if ($item->getPrice() == 0 && !empty($item->getParentItem())) {
                    // Skip product variants; products variants get added to the order items as separate items, filter them out.
                    continue;
                }

                $itemPrefix = $prefix . '.itemDetailLine';
                $requestBody['additionalData'][$itemPrefix . $itemIndex . '.description'] = $item->getName();
                $requestBody['additionalData'][$itemPrefix . $itemIndex . '.unitPrice'] = $item->getPrice(); // convert to minor units
                $requestBody['additionalData'][$itemPrefix . $itemIndex . '.discountAmount'] = $item->getDiscountAmount(); // convert to minor units
                $requestBody['additionalData'][$itemPrefix . $itemIndex . '.commodityCode'] = $item->getQuoteItemId();
                $requestBody['additionalData'][$itemPrefix . $itemIndex . '.quantity'] = $item->getQtyOrdered();
                $requestBody['additionalData'][$itemPrefix . $itemIndex . '.productCode'] = $item->getSku();
                $requestBody['additionalData'][$itemPrefix . $itemIndex . '.totalAmount'] = $item->getRowTotal(); // convert to minor units

                $itemIndex++;
            }
        }

        return ['body' => $requestBody];
    }
}

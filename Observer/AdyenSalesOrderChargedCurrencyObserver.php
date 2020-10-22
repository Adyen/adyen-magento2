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
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\ChargedCurrency;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class AdyenSalesOrderChargedCurrencyObserver implements ObserverInterface
{

    /**
     * @var ChargedCurrency $chargedCurrency
     */
    private $chargedCurrency;

    public function __construct(
        ChargedCurrency $chargedCurrency
    ) {
        $this->chargedCurrency = $chargedCurrency;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        $paymentMethod = $order->getPayment()->getMethod();
        if (strpos($paymentMethod, 'adyen_') !== false) {
            $order->setAdyenChargedCurrency($this->chargedCurrency->getOrderAmountCurrency($order)->getCurrencyCode());
        }
    }
}

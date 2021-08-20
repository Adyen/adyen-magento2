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
 * Copyright (c) 2021 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Data\Order;

use Magento\Payment\Gateway\Data\Order\AddressAdapterFactory;
use Magento\Sales\Model\Order;

class OrderAdapter extends \Magento\Payment\Gateway\Data\Order\OrderAdapter
{
    /**
     * @var Order
     */
    private $order;

    /**
     * @param Order $order
     * @param AddressAdapterFactory $addressAdapterFactory
     */
    public function __construct(
        Order $order,
        AddressAdapterFactory $addressAdapterFactory
    ) {
        $this->order = $order;
        parent::__construct($order, $addressAdapterFactory);
    }

    public function getQuoteId()
    {
        return $this->order->getQuoteId();
    }
}

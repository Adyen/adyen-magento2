<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Data\Order;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use PayPal\Braintree\Gateway\Data\Order\AddressAdapterFactory;

class OrderAdapter extends \PayPal\Braintree\Gateway\Data\Order\OrderAdapter
{
    /**
     * @var Order
     */
    private $order;

    /**
     * @param Order $order
     * @param CartRepositoryInterface $quoteRepository
     * @param AddressAdapterFactory $addressAdapterFactory
     */
    public function __construct(
        Order $order,
        CartRepositoryInterface $quoteRepository,
        AddressAdapterFactory $addressAdapterFactory
    ) {
        $this->order = $order;
        parent::__construct($order, $quoteRepository, $addressAdapterFactory);
    }

    public function getQuoteId()
    {
        return $this->order->getQuoteId();
    }
}

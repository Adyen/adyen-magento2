<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Data\Order;

use Adyen\Payment\Api\Data\AddressAdapterInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

class AddressAdapter extends \Magento\Payment\Gateway\Data\Order\AddressAdapter implements AddressAdapterInterface
{
    private OrderAddressInterface $address;

    public function __construct(OrderAddressInterface $address)
    {
        $this->address = $address;

        parent::__construct($address);
    }

    public function getStreetLine3(): string
    {
        $street = $this->address->getStreet();

        return $street[2] ?? '';
    }

    public function getStreetLine4(): string
    {
        $street = $this->address->getStreet();

        return $street[3] ?? '';
    }
}

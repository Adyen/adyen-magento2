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
 * Copyright (c) 2020 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Data\Order;

use Adyen\Payment\Api\Data\AddressAdapterInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

class AddressAdapter extends \Magento\Payment\Gateway\Data\Order\AddressAdapter implements AddressAdapterInterface
{
    /**
     * @var OrderAddressInterface
     */
    private $address;

    public function __construct(OrderAddressInterface $address)
    {
        $this->address = $address;
        parent::__construct($address);
    }

    public function getStreetLine3()
    {
        $street = $this->address->getStreet();
        return isset($street[2]) ? $street[2] : '';
    }

    public function getStreetLine4()
    {
        $street = $this->address->getStreet();
        return isset($street[3]) ? $street[3] : '';
    }
}

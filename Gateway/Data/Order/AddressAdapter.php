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

use Magento\Sales\Api\Data\OrderAddressInterface;

class AddressAdapter extends \Magento\Payment\Gateway\Data\Order\AddressAdapter
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

    /**
     * Get street line 3
     *
     * @return string
     */
    public function getStreetLine3()
    {
        $street = $this->address->getStreet();
        return isset($street[2]) ? $street[2] : '';
    }

    /**
     * Get street line 4
     *
     * @return string
     */
    public function getStreetLine4()
    {
        $street = $this->address->getStreet();
        return isset($street[3]) ? $street[3] : '';
    }
}

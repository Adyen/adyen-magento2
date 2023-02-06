<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Data;

use Magento\Payment\Gateway\Data\AddressAdapterInterface as CoreAddressAdapterInterface;

interface AddressAdapterInterface extends CoreAddressAdapterInterface
{
    /**
     * @return string
     */
    public function getStreetLine3(): string;

    /**
     * @return string
     */
    public function getStreetLine4(): string;
}

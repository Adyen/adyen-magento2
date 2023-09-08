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

namespace Adyen\Payment\Block\Info;

class Giftcard extends AbstractInfo
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::info/adyen_giftcard.phtml';
}

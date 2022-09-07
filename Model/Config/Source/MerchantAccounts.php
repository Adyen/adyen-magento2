<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

use Adyen\Payment\Helper\Data;
use Magento\Framework\Data\OptionSourceInterface;

class MerchantAccounts implements OptionSourceInterface
{
    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * MerchantAccounts constructor.
     *
     * @param Data $dataHelper
     */
    public function __construct(
        Data $dataHelper
    ) {
        $this->dataHelper = $dataHelper;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $merchantAccount = $this->dataHelper->getAdyenMerchantAccount('adyen_cc');
        return $merchantAccount ? array(
            ['value' => $merchantAccount, 'label' => $merchantAccount]
        ) : [];
    }
}

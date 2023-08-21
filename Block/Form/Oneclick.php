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

namespace Adyen\Payment\Block\Form;

use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Form;

/**
 * @deprecated
 */
class Oneclick extends Form
{
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }
}

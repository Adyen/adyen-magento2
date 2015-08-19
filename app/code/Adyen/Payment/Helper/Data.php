<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Adyen\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Data extends AbstractHelper
{

    public function getRecurringTypes() {

        return [
            \Adyen\Payment\Model\RecurringType::ONECLICK => 'ONECLICK',
            \Adyen\Payment\Model\RecurringType::ONECLICK_RECURRING => 'ONECLICK,RECURRING',
            \Adyen\Payment\Model\RecurringType::RECURRING => 'RECURRING'
        ];
    }

}
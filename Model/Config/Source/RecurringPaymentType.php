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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Recurring;
use Magento\Framework\Data\OptionSourceInterface;

class RecurringPaymentType implements OptionSourceInterface
{
    const UNDEFINED_OPTION_LABEL = 'NONE';

    /**
     * @var Data
     */
    protected $_adyenHelper;

    /**
     * RecurringPaymentType constructor.
     *
     * @param Data $adyenHelper
     */
    public function __construct(
        Data $adyenHelper
    ) {
        $this->_adyenHelper = $adyenHelper;
    }

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        $recurringTypes = Recurring::getRecurringTypes();

        foreach ($recurringTypes as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }

        return $options;
    }
}

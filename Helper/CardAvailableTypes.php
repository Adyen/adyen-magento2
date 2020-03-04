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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class CardAvailableTypes extends AbstractHelper
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    public function __construct(\Adyen\Payment\Helper\Data $adyenHelper)
    {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * Retrieve available card types
     *
     * @param string $index Either 'name' or 'code_alt' to retrieve that information from the card types
     *
     * @return array
     */
    public function getCardAvailableTypes($index = 'name')
    {
        $types = [];
        $cardTypes = $this->adyenHelper->getAdyenCcTypes();
        $enableAvailableTypes = $this->adyenHelper->getAdyenCcConfigData('enablecctypes');
        if (!$enableAvailableTypes) {
            return $types;
        }

        $availableTypes = $this->adyenHelper->getAdyenCcConfigData('cctypes');
        if (!$availableTypes) {
            return $types;
        }

        $availableTypes = explode(',', $availableTypes);
        foreach (array_keys($cardTypes) as $code) {
            if (in_array($code, $availableTypes)) {
                $types[$code] = $cardTypes[$code][$index];
            }
        }

        return $types;
    }

}

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

namespace Adyen\Payment\Model\Config\Source;

class KarCaptureMode implements \Magento\Framework\Option\ArrayInterface
{
    const OPTIONS = [
        [
            'value' => 'capture_on_shipment',
            'label' => 'Capture on shipment'
        ],
        [
            'value' => 'capture_immediately',
            'label' => 'Capture immediately'
        ],
        [
            'value' => 'capture_manually',
            'label' => 'Capture manually'
        ],
    ];

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return self::OPTIONS;
    }
}

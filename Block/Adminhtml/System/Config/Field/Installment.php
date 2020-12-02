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

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

class Installment extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * Options
     *
     * @var array
     */
    protected $_options = [
        '1' => '1x',
        '2' => '2x',
        '3' => '3x',
        '4' => '4x',
        '5' => '5x',
        '6' => '6x',
        '7' => '7x',
        '8' => '8x',
        '9' => '9x',
        '10' => '10x',
        '11' => '11x',
        '12' => '12x',
        '13' => '13x',
        '14' => '14x',
        '15' => '15x',
        '16' => '16x',
        '17' => '17x',
        '18' => '18x',
        '19' => '19x',
        '20' => '20x',
        '21' => '21x',
        '22' => '22x',
        '23' => '23x',
        '24' => '24x'
    ];

    /**
     * Sets name for input element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }
}

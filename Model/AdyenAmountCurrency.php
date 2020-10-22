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

namespace Adyen\Payment\Model;

class AdyenAmountCurrency
{
    protected $amount;

    protected $discountAmount;

    protected $taxAmount;

    protected $currencyCode;

    public function __construct($amount, $currencyCode, $discountAmount = 0, $taxAmount = 0)
    {
        $this->amount = $amount;
        $this->currencyCode = $currencyCode;
        $this->discountAmount = $discountAmount;
        $this->taxAmount = $taxAmount;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    public function getDiscountAmount()
    {
        return $this->discountAmount;
    }

    public function getTaxAmount()
    {
        return $this->taxAmount;
    }
}

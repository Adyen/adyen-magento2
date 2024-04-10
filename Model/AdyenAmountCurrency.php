<?php
/**
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

    protected $amountIncludingTax;

    protected $discountAmount;

    protected $taxAmount;

    protected $currencyCode;

    protected $amountDue;

    protected $discountTaxCompensationAmount;

    public function __construct(
        $amount,
        $currencyCode,
        $discountAmount = 0,
        $taxAmount = 0,
        $amountDue = 0,
        $amountIncludingTax = 0,
        $discountTaxCompensationAmount = 0
    ) {
        $this->amount = $amount;
        $this->amountIncludingTax = $amountIncludingTax;
        $this->currencyCode = $currencyCode;
        $this->discountAmount = $discountAmount;
        $this->taxAmount = $taxAmount;
        $this->amountDue = $amountDue;
        $this->discountTaxCompensationAmount = $discountTaxCompensationAmount;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getAmountIncludingTax()
    {
        return $this->amountIncludingTax;
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

    public function getAmountDue()
    {
        return $this->amountDue;
    }

    public function getDiscountTaxCompensationAmount()
    {
        return $this->discountTaxCompensationAmount;
    }

    public function getAmountIncludingTaxWithDiscount()
    {
        if ($this->getDiscountTaxCompensationAmount() > 0) {
            return $this->getAmountIncludingTax() - $this->getDiscountAmount();
        } else {
            $taxCompensation = $this->getDiscountAmount() * $this->getCalculatedTaxPercentage() / 100;
            return $this->getAmountIncludingTax() - $this->getDiscountAmount() - $taxCompensation;
        }
    }

    public function getAmountWithDiscount()
    {
        return $this->getAmount() - $this->getDiscountAmount() + $this->getDiscountTaxCompensationAmount();
    }

    public function getCalculatedTaxPercentage()
    {
        if ($this->getAmountWithDiscount() > 0) {
            return ($this->getTaxAmount() / $this->getAmountWithDiscount()) * 100;
        } else {
            return 0;
        }
    }
}

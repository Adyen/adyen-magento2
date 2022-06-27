<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\PaymentMethod;


use Adyen\Payment\Api\Data\AdyenPaymentMethodInterface;
use Magento\Framework\Model\AbstractModel;

class AdyenPaymentMethod extends AbstractModel implements AdyenPaymentMethodInterface
{

    public function getPaymentMethod(): string
    {
        return $this->getData(self::PAYMENT_METHOD);
    }

    public function setPaymentMethod($paymentMethod)
    {
        $this->setData(self::PAYMENT_METHOD, $paymentMethod);
    }

    public function getEnableRecurring(): bool
    {
        return $this->getData(self::ENABLE_RECURRING);
    }

    public function setEnableRecurring($enableRecurring)
    {
        $this->setData(self::ENABLE_RECURRING, $enableRecurring);
    }

    public function getActive(): bool
    {
        return $this->getData(self::ACTIVE);
    }

    public function setActive($active)
    {
        $this->setData(self::ACTIVE, $active);
    }
}
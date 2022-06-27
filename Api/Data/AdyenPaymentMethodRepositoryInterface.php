<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Data;

use Adyen\Payment\Model\PaymentMethod\AdyenPaymentMethod;
use Magento\Framework\Exception\NoSuchEntityException;

interface AdyenPaymentMethodRepositoryInterface
{
    /**
     * @param string $paymentMethod
     * @return AdyenPaymentMethod
     * @throws NoSuchEntityException
     */
    public function getByPaymentMethodName(string $paymentMethod): AdyenPaymentMethod;
}

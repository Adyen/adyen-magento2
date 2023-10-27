<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Magento\Framework\Model\AbstractModel;

class PaymentResponse extends AbstractModel implements PaymentResponseInterface
{

    protected function _construct()
    {
        $this->_init(ResourceModel\PaymentResponse::class);
    }

    public function getMerchantReference(): ?string
    {
        return $this->getData(self::MERCHANT_REFERENCE);
    }

    public function setMerchantReference(string $merchantReference): PaymentResponseInterface
    {
        return $this->setData(self::MERCHANT_REFERENCE, $merchantReference);
    }

    public function getResultCode(): ?string
    {
        return $this->getData(self::RESULT_CODE);
    }

    public function setResultCode(string $resultCode): PaymentResponseInterface
    {
        return $this->setData(self::RESULT_CODE, $resultCode);
    }

    public function getResponse(): ?string
    {
        return $this->getData(self::RESPONSE);
    }

    public function setResponse(string $response): PaymentResponseInterface
    {
        return $this->setData(self::RESPONSE, $response);
    }
}

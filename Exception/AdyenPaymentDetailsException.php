<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Exception;

use Adyen\Payment\Enum\PaymentDetailsError;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Phrase;

class AdyenPaymentDetailsException extends ValidatorException
{
    public function __construct(
        Phrase $phrase,
        protected ?PaymentDetailsError $detailsError = null,
        \Exception $cause = null,
        $code = 0
    ) {
        parent::__construct($phrase, $cause, $code);
    }

    /**
     * @return \Adyen\Payment\Enum\PaymentDetailsError|null
     */
    public function getErrorType(): ?PaymentDetailsError
    {
        return $this->detailsError;
    }
}
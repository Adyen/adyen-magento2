<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Validator;

use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class PaymentLinksRequestValidator extends AbstractValidator
{
    /**
     * @param array $validationSubject
     * @return mixed
     */
    public function validate(array $validationSubject)
    {
        $payment = $validationSubject['payment'];
        $expiresAt = $payment->getAdyenPblExpiresAt();

        $isValid = true;
        $errorMessages = [];

        if ($expiresAt && $expiryDate = date_create_from_format(AdyenPayByLinkConfigProvider::DATE_TIME_FORMAT, $expiresAt)) {
            $daysToExpire = ($expiryDate->getTimestamp() - time()) / 86400;

            if ($daysToExpire <= AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS ||
                $daysToExpire >= AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS
            ) {
                $isValid = false;
                $errorMessages[] = 'Invalid expiry date selected for Adyen Pay By Link';
            }
        }

        return $this->createResult($isValid, $errorMessages);
    }
}

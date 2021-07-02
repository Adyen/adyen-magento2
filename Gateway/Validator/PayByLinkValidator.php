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
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Validator;

use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Payment\Gateway\Validator\AbstractValidator;

class PayByLinkValidator extends AbstractValidator
{
    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $payment = $validationSubject['payment'];
        $expiryDate = date_create_from_format(
            AdyenPayByLinkConfigProvider::DATE_FORMAT,
            $payment->getAdyenPblExpiresAt()
        );
        if ($expiryDate) {
            $daysToExpire = (new \DateTime())->diff($expiryDate)->format("%r%a");
            if (
                $daysToExpire <= AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS ||
                $daysToExpire >= AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS
            ) {
                return $this->createResult(false, ['Invalid expiry date selected for Adyen Pay By Link']);
            }
        }
        return $this->createResult(true);
    }
}

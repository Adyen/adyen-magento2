<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Validator;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class DonateResponseValidator extends AbstractValidator
{
    const VALID_RESULT_CODES = ['Authorised', 'Received'];

    /**
     * @param ResultInterfaceFactory $resultInterfaceFactory
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        ResultInterfaceFactory $resultInterfaceFactory,
        private readonly AdyenLogger $adyenLogger
    ) {
        parent::__construct($resultInterfaceFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);

        $isValid = true;
        $errorMessages = [];

        if (empty($response['payment']['resultCode']) ||
            !in_array($response['payment']['resultCode'], self::VALID_RESULT_CODES)) {
            if (!empty($response['error'])) {
                $this->adyenLogger->error(
                    sprintf("An error occurred with the donation: %s", $response['error'])
                );
            }

            $isValid = false;
            $errorMessages[] = __('An error occurred with the donation.');
        }

        return $this->createResult($isValid, $errorMessages);
    }
}

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

use Adyen\Model\Checkout\PaymentResponse;
use Adyen\Payment\Exception\AbstractAdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class CheckoutResponseValidator extends AbstractValidator
{
    const VALID_RESULT_CODES = [
        PaymentResponse::RESULT_CODE_AUTHORISED,
        PaymentResponse::RESULT_CODE_RECEIVED,
        PaymentResponse::RESULT_CODE_IDENTIFY_SHOPPER,
        PaymentResponse::RESULT_CODE_CHALLENGE_SHOPPER,
        PaymentResponse::RESULT_CODE_PRESENT_TO_SHOPPER,
        PaymentResponse::RESULT_CODE_PENDING,
        PaymentResponse::RESULT_CODE_REDIRECT_SHOPPER
    ];

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly AdyenLogger $adyenLogger
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     * @throws ValidatorException
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $responseCollection = SubjectReader::readResponse($validationSubject);

        if (empty($responseCollection)) {
            throw new ValidatorException(__("No responses were provided"));
        }

        foreach ($responseCollection as $response) {
            if (empty($response['resultCode'])) {
                $this->handleEmptyResultCode($response);
            } else {
                $this->validateResultCode($response['resultCode']);
            }
        }

        return $this->createResult(true);
    }

    /**
     * @throws ValidatorException
     */
    private function validateResultCode(string $resultCode): void
    {
        if (strcmp($resultCode, PaymentResponse::RESULT_CODE_REFUSED) === 0) {
            $errorMsg = __('The payment is REFUSED.');
            // this will result the specific error
            throw new ValidatorException($errorMsg);
        } elseif (!in_array($resultCode, self::VALID_RESULT_CODES, true)) {
            $errorMsg = __('Error with payment method, please select a different payment method.');
            throw new ValidatorException($errorMsg);
        }
    }

    /**
     * @throws ValidatorException
     */
    private function handleEmptyResultCode(array $response): void
    {
        if (!empty($response['error'])) {
            $this->adyenLogger->error($response['error']);
        }

        if (!empty($response['errorCode']) &&
            !empty($response['error']) &&
            in_array($response['errorCode'], AbstractAdyenException::SAFE_ERROR_CODES, true)) {
            $errorMsg = __($response['error']);
        } else {
            $errorMsg = __('Error with payment method, please select a different payment method.');
        }

        throw new ValidatorException($errorMsg);
    }
}

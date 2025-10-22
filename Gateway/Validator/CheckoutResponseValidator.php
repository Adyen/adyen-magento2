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
use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
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
     * @param OrdersApi $ordersApi
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly AdyenLogger $adyenLogger,
        private readonly OrdersApi $ordersApi
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $responseCollection = SubjectReader::readResponse($validationSubject);

        $errorCodes = [];

        if (empty($responseCollection)) {
            $errorCodes[] = 'authError_empty_response';
        } else {
            foreach ($responseCollection as $response) {
                if (empty($response['resultCode'])) {
                    $errorCodes[] = $this->handleEmptyResultCode($response);
                } else {
                    $errorMessage = $this->validateResultCode($response['resultCode']);
                    if (isset($errorMessage)) {
                        $errorCodes[] = $errorMessage;
                    }
                }
            }
        }

        // Cancel Checkout API Order in case of partial payments if the payment is refused
        $ordersResponse = $this->ordersApi->getCheckoutApiOrder();
        if (!empty($errorCodes) && isset($ordersResponse)) {
            $paymentData = SubjectReader::readPayment($validationSubject);
            $order = $paymentData->getPayment()->getOrder();

            $this->ordersApi->cancelOrder($order, $ordersResponse['pspReference'], $ordersResponse['orderData']);
        }

        // Gateway's error code mapping is being used. Please check `etc/authorize_error_mapping.xml` for details.
        return $this->createResult(empty($errorCodes), [], $errorCodes);
    }

    /**
     * Returns `null` if the resultCode is valid. Otherwise, returns a string with the error code.
     *
     * @param string $resultCode
     * @return string|null
     */
    private function validateResultCode(string $resultCode): ?string
    {
        if (strcmp($resultCode, PaymentResponse::RESULT_CODE_REFUSED) === 0) {
            $errorCode = 'authError_refused';
        } elseif (strcmp($resultCode, PaymentResponseHandler::GIFTCARD_REFUSED) === 0) {
            $errorCode = 'authError_giftcard_refused';
        } elseif (!in_array($resultCode, self::VALID_RESULT_CODES, true)) {
            $errorCode = 'authError_generic';
        }

        return $errorCode ?? null;
    }

    /**
     * @param array $response
     * @return string
     */
    private function handleEmptyResultCode(array $response): string
    {
        if (!empty($response['error'])) {
            $this->adyenLogger->error($response['error']);
        }

        if (!empty($response['errorCode']) &&
            !empty($response['error']) &&
            in_array($response['errorCode'], AbstractAdyenException::SAFE_ERROR_CODES, true)) {
            return $response['errorCode'];
        } else {
            $errorCode = 'authError_generic';
        }

        return $errorCode;
    }
}

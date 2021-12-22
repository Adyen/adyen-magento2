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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Validator;

use Adyen\Payment\Gateway\Http\Client\TransactionCapture;
use Magento\Payment\Gateway\Validator\AbstractValidator;

class CaptureResponseValidator extends AbstractValidator
{
    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * CaptureResponseValidator constructor.
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        $this->adyenLogger = $adyenLogger;
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $response = \Magento\Payment\Gateway\Helper\SubjectReader::readResponse($validationSubject);

        $isValid = true;
        $errorMessages = [];

        if (array_key_exists(TransactionCapture::MULTIPLE_AUTHORIZATIONS, $response)) {
            return $this->validateMultipleCaptureRequests($response);
        }

        if (empty($response['response']) || $response['response'] != TransactionCapture::CAPTURE_RECEIVED) {
            $errorMessages[] = $this->buildErrorMessages($response);
            $isValid = false;
        }

        return $this->createResult($isValid, $errorMessages);
    }

    /**
     * Validate a response which contains multiple capture responses
     *
     * @param $responseContainer
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validateMultipleCaptureRequests($responseContainer)
    {
        $isValid = true;
        $errorMessages = [];

        foreach ($responseContainer[TransactionCapture::MULTIPLE_AUTHORIZATIONS] as $response) {
            if (empty($response['response']) || $response['response'] != TransactionCapture::CAPTURE_RECEIVED) {
                $errorMessages[] = $this->buildErrorMessages($response, true);
            }
        }

        return $this->createResult($isValid, $errorMessages);
    }

    /**
     * @param $response
     * @param bool $multiple
     * @return \Magento\Framework\Phrase|string
     */
    private function buildErrorMessages($response, bool $multiple = false)
    {
        if ($multiple && array_key_exists(TransactionCapture::CAPTURE_AMOUNT, $response)) {
            $errorMsg = __('Error with capture on transaction with amount') . $response[TransactionCapture::CAPTURE_AMOUNT];
        } else {
            $errorMsg = __('Error with capture');
        }

        $this->adyenLogger->error($errorMsg);

        if (!empty($response['error'])) {
            $this->adyenLogger->error($response['error']);
        }

        return $errorMsg;
    }
}

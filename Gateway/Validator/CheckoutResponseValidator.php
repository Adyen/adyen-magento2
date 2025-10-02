<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Validator;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Sales\Model\Order\Payment;

class CheckoutResponseValidator extends AbstractValidator
{
    private AdyenLogger $adyenLogger;
    private Data $adyenHelper;
    private array $errorCodes = [];

    /**
     * @var array
     */
    const ALLOWED_ERROR_CODES = ['124'];

    /**
     * CheckoutResponseValidator constructor.
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param AdyenLogger $adyenLogger
     * @param Data $adyenHelper
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        AdyenLogger $adyenLogger,
        Data $adyenHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        // Extract all the payment responses
        $responseCollection = $validationSubject['response'];
        unset($validationSubject['response']);

        // hasOnlyGiftCards is needed later but cannot be processed as a response
        unset($responseCollection['hasOnlyGiftCards']);

        // Assign the remaining items to $commandSubject
        $commandSubject = $validationSubject;

        if (empty($responseCollection)) {
            $this->errorCodes[] = 'authError_empty_response';
        }

        try {
            foreach ($responseCollection as $thisResponse) {
                $responseSubject = array_merge($commandSubject, ['response' => $thisResponse]);
                $this->validateResponse($responseSubject);
            }
        } catch (Exception $e) {
            $this->adyenLogger->error(
                sprintf("An error occurred while processing payment response: %s", $e->getMessage())
            );
            $this->errorCodes[] = 'authError_generic';
        }

        return $this->createResult(empty($this->errorCodes), [], $this->errorCodes);
    }

    /**
     * @param array $responseSubject
     * @return void
     * @throws LocalizedException
     */
    private function validateResponse(array $responseSubject): void
    {
        $response = SubjectReader::readResponse($responseSubject);
        $paymentDataObjectInterface = SubjectReader::readPayment($responseSubject);
        /** @var Payment $payment */
        $payment = $paymentDataObjectInterface->getPayment();

        $payment->setAdditionalInformation('3dActive', false);

        // Handle empty result for unexpected cases
        if (empty($response['resultCode'])) {
            $this->handleEmptyResultCode($response);
        } else {
            // Handle the `/payments` response
            $this->validateResult($response, $payment);
        }
    }

    /**
     * @param array $response
     * @param Payment $payment
     * @return void
     * @throws LocalizedException
     */
    private function validateResult(array $response, Payment $payment): void
    {
        $resultCode = $response['resultCode'];
        $payment->setAdditionalInformation('resultCode', $resultCode);

        if (!empty($response['action'])) {
            $payment->setAdditionalInformation('action', $response['action']);
        }

        if (!empty($response['additionalData'])) {
            $payment->setAdditionalInformation('additionalData', $response['additionalData']);
        }

        if (!empty($response['pspReference'])) {
            $payment->setAdditionalInformation('pspReference', $response['pspReference']);
        }

        if (!empty($response['details'])) {
            $payment->setAdditionalInformation('details', $response['details']);
        }

        if (!empty($response['donationToken'])) {
            $payment->setAdditionalInformation('donationToken', $response['donationToken']);
        }

        switch ($resultCode) {
            case "Authorised":
            case "Received":
                // Save cc_type if available in the response
                if (!empty($response['additionalData']['paymentMethod'])) {
                    $ccType = $this->adyenHelper->getMagentoCreditCartType(
                        $response['additionalData']['paymentMethod']
                    );
                    $payment->setAdditionalInformation('cc_type', $ccType);
                    $payment->setCcType($ccType);
                }
                break;
            case "IdentifyShopper":
            case "ChallengeShopper":
            case "PresentToShopper":
            case 'Pending':
            case "RedirectShopper":
                // nothing extra
                break;
            case "Refused":
                // this will result the specific error
                $this->errorCodes[] = 'authError_refused';
                break;
            default:
                $this->errorCodes[] = 'authError_generic';
        }
    }

    /**
     * @param array $response
     * @return void
     */
    private function handleEmptyResultCode(array $response): void
    {
        if (!empty($response['error'])) {
            $this->adyenLogger->error($response['error']);
        }

        if (isset($response['errorCode']) && in_array($response['errorCode'], self::ALLOWED_ERROR_CODES)) {
            $this->errorCodes[] = $response['errorCode'];
        } else {
            $this->errorCodes[] = 'authError_generic';
        }
    }
}

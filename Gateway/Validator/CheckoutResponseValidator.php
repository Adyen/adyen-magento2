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
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class CheckoutResponseValidator extends AbstractValidator
{
    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var Array
     */
    final const ALLOWED_ERROR_CODES = ['124'];

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
    public function validate(array $validationSubject)
    {
        $response = SubjectReader::readResponse($validationSubject);
        $paymentDataObjectInterface = SubjectReader::readPayment($validationSubject);
        $payment = $paymentDataObjectInterface->getPayment();

        $payment->setAdditionalInformation('3dActive', false);
        $isValid = true;
        $errorMessages = [];

        // validate result
        if (!empty($response['resultCode'])) {
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
                    $errorMsg = __('The payment is REFUSED.');
                    // this will result the specific error
                    throw new LocalizedException($errorMsg);
                default:
                    $errorMsg = __('Error with payment method please select different payment method.');
                    throw new LocalizedException($errorMsg);
            }
        } else {
            if (!empty($response['error'])) {
                $this->adyenLogger->error($response['error']);
            }

            if (!empty($response['errorCode']) && !empty($response['error']) && in_array($response['errorCode'], self::ALLOWED_ERROR_CODES, true)) {
                $errorMsg = __($response['error']);
            } else {
                $errorMsg = __('Error with payment method, please select a different payment method.');
            }

            throw new LocalizedException($errorMsg);
        }

        return $this->createResult($isValid, $errorMessages);
    }
}

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
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;

class ThreeDS2ResponseValidator extends AbstractValidator
{
    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * GeneralResponseValidator constructor.
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
        if (!empty($validationSubject['payment'])) {
            $payment = $validationSubject['payment'];
        } else {
            $errorMsg = __('Error with payment method during validation please select different payment method.');
            throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
        }

        $isValid = true;
        $errorMessages = [];

        // validate result
        if (!empty($response['resultCode'])) {
            // 3DS2.0 should have IdentifyShopper or ChallengeShopper as a resultCode
            switch ($response['resultCode']) {
                case "IdentifyShopper":
                    if (!empty($response['authentication']['threeds2.fingerprintToken'])) {
                        $payment->setAdditionalInformation('threeDS2Type', $response['resultCode']);
                        $payment->setAdditionalInformation('threeDS2Token', $response['authentication']['threeds2.fingerprintToken']);
                        $payment->setAdditionalInformation('threeDS2PaymentData', $response['paymentData']);
                    }
                    break;
                case "ChallengeShopper":
                    if (!empty($response['authentication']['threeds2.challengeToken'])) {
                        $payment->setAdditionalInformation('threeDS2Type', $response['resultCode']);
                        $payment->setAdditionalInformation('threeDS2Token', $response['authentication']['threeds2.challengeToken']);
                        $payment->setAdditionalInformation('threeDS2PaymentData', $response['paymentData']);
                    }
                    break;
                default:
                    $errorMsg = __('Error with payment method please select different payment method.');
                    throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
                    break;
            }
        } else {
            $errorMsg = __('Error with payment method please select different payment method.');
            throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
        }

        return $this->createResult($isValid, $errorMessages);
    }
}
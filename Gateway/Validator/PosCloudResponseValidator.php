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
 * Adyen Payment Module
 *
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;

class PosCloudResponseValidator extends AbstractValidator
{
    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * PosCloudResponseValidator constructor.
     *
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate(array $validationSubject)
    {
        $errorMessages = [];
        $isValid = true;
        $response = \Magento\Payment\Gateway\Helper\SubjectReader::readResponse($validationSubject);
        $paymentDataObjectInterface = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($validationSubject);
        $payment = $paymentDataObjectInterface->getPayment();

        $this->adyenLogger->addAdyenDebug(print_r($response, true));

        // Check for errors
        if (!empty($response['error'])) {
            if (!empty($response['code']) && $response['code'] == CURLE_OPERATION_TIMEOUTED) {
                // If the initiate call resulted in a timeout, do a status call(try to place an order)
                return $this->createResult($isValid, $errorMessages);
            } else {
                // There is an error which is not a timeout, stop the transaction and show the error
                $this->adyenLogger->error(json_encode($response));
                throw new \Magento\Framework\Exception\LocalizedException(__($response['error']));
            }
        } else {
            // We have a paymentResponse from the terminal
            $paymentResponse = $response;
        }

        if (!empty($paymentResponse) && $paymentResponse['Response']['Result'] != 'Success') {
            $errorMsg = __($paymentResponse['Response']['ErrorCondition']);
            $this->adyenLogger->error($errorMsg);
            $this->adyenLogger->error(json_encode($response));
            throw new \Magento\Framework\Exception\LocalizedException(__("The transaction could not be completed."));
        }

        if (!empty($paymentResponse['PaymentReceipt'])) {
            $formattedReceipt = $this->adyenHelper->formatTerminalAPIReceipt($paymentResponse['PaymentReceipt']);
            $payment->setAdditionalInformation('receipt', $formattedReceipt);
        }
        return $this->createResult($isValid, $errorMessages);
    }
}

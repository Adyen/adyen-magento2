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
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
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
            if (strpos($response['error'], "Could not connect") !== false) {
                // Do the status call(try to place an order)
                return $this->createResult($isValid, $errorMessages);
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__($response['error']));
            }
        }

        // We receive a SaleToPOIRequest when the terminal is not reachable
        if (!empty($response['SaleToPOIRequest'])){
            throw new \Magento\Framework\Exception\LocalizedException(__("The terminal could not be reached."));
        }

        // Check if Status or PaymentResponse
        if (!empty($response['SaleToPOIResponse']['TransactionStatusResponse'])) {
            $statusResponse = $response['SaleToPOIResponse']['TransactionStatusResponse'];
            if ($statusResponse['Response']['Result'] == 'Failure') {
                $errorMsg = __('In Progress');
                throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
            } else {
                $paymentResponse = $statusResponse['RepeatedMessageResponse']['RepeatedResponseMessageBody']['PaymentResponse'];
            }
        } else {
            $paymentResponse = $response['SaleToPOIResponse']['PaymentResponse'];
        }

        if (!empty($paymentResponse) && $paymentResponse['Response']['Result'] != 'Success') {
            $errorMsg = __($paymentResponse['Response']['ErrorCondition']);
            $this->adyenLogger->error($errorMsg);
            throw new \Magento\Framework\Exception\LocalizedException(__("The transaction could not be completed."));
        }

        if (!empty($paymentResponse['PaymentReceipt'])) {
            $formattedReceipt = $this->adyenHelper->formatTerminalAPIReceipt(json_encode($paymentResponse['PaymentReceipt']));
            $payment->setAdditionalInformation('receipt', $formattedReceipt);
        }
        return $this->createResult($isValid, $errorMessages);
    }
}
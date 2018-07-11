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
     * PosCloudResponseValidator constructor.
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
        $errorMessages = [];
        $isValid = true;
        $response = \Magento\Payment\Gateway\Helper\SubjectReader::readResponse($validationSubject);

        // Check In Progress status call
        if (!empty($response['SaleToPOIResponse']['TransactionStatusResponse']['Response']['Result']) && $response['SaleToPOIResponse']['TransactionStatusResponse']['Response']['Result'] == "Failure") {
            $errorMsg = __('In Progress');
            $errorMessages[] = $errorMsg;
            throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
        }
        elseif ((!empty($response['SaleToPOIResponse']['PaymentResponse']['Response']['Result']) &&
                $response['SaleToPOIResponse']['PaymentResponse']['Response']['Result'] != 'Success'
            ) || empty($response['SaleToPOIResponse']['PaymentResponse']['Response']['Result'])
        ) {
            $errorMsg = __('Problem with POS terminal');
            $this->adyenLogger->error($errorMsg);
            $errorMessages[] = $errorMsg;
            $isValid = true;
        }
        return $this->createResult($isValid, $errorMessages);
    }
}
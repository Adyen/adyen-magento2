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

use Magento\Payment\Gateway\Validator\AbstractValidator;

class GeneralResponseValidator extends AbstractValidator
{
    /**
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $response = \Magento\Payment\Gateway\Helper\SubjectReader::readResponse($validationSubject);
        $paymentDataObjectInterface = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($validationSubject);
        $payment = $paymentDataObjectInterface->getPayment();

        $isValid = true;
        $errorMessages = [];

        // validate result
        if ($response) {
            switch ($response['resultCode']) {
                case "Authorised":
                    $payment->setAdditionalInformation('pspReference', $response['pspReference']);
                    break;
                case "Refused":
                    $errorMsg = __('The payment is REFUSED.');
                    $this->_logger->critical($errorMsg);
                    $errorMessages[] = $errorMsg;
                    break;
                default:
                    $errorMsg = __('Error with payment method please select different payment method.');
                    $this->_logger->critical($errorMsg);
                    $errorMessages[] = $errorMsg;
                    break;
            }
        } else {
            $errorMsg = __('Error with payment method please select different payment method.');
            $this->_logger->critical($errorMsg);
            $errorMessages[] = $errorMsg;
        }
        
        return $this->createResult($isValid, $errorMessages);
    }
}
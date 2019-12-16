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

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;

class CcAuthorizationDataBuilder implements BuilderInterface
{

    /**
     * @param array $buildSubject
     * @return mixed
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $requestBody = [];
        // If ccType is set use this. For bcmc you need bcmc otherwise it will fail
        $requestBody['paymentMethod']['type'] = "scheme";
        if ($cardNumber = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER)) {
            $requestBody['paymentMethod']['encryptedCardNumber'] = $cardNumber;
        }
        if ($expiryMonth = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH)) {
            $requestBody['paymentMethod']['encryptedExpiryMonth'] = $expiryMonth;
        }
        if ($expiryYear = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR)) {
            $requestBody['paymentMethod']['encryptedExpiryYear'] = $expiryYear;
        }
        if ($holderName = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::HOLDER_NAME)) {
            $requestBody['paymentMethod']['holderName'] = $holderName;
        }
        if ($securityCode = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE)) {
            $requestBody['paymentMethod']['encryptedSecurityCode'] = $securityCode;
        }
        // Remove from additional data
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::HOLDER_NAME);

        // if installments is set and card type is credit card add it into the request
        $numberOfInstallments = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) ?: 0;
        $comboCardType = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::COMBO_CARD_TYPE) ?: 'credit';
        if ($numberOfInstallments > 0) {
            $requestBody['installments']['value'] = $numberOfInstallments;
        }
        // if card type is debit then change the issuer type and unset the installments field
        if ($comboCardType == 'debit') {
            if ($selectedDebitBrand = $this->getSelectedDebitBrand($payment->getAdditionalInformation('cc_type'))) {
                $requestBody['additionalData']['overwriteBrand'] = true;
                $requestBody['selectedBrand'] = $selectedDebitBrand;
                $requestBody['paymentMethod']['type'] = $selectedDebitBrand;
            }
            unset($requestBody['installments']);
        }
        $request['body'] = $requestBody;
        return $request;
    }

    /**
     * @param string $brand
     * @return string
     */
    private function getSelectedDebitBrand($brand)
    {
        if ($brand == 'VI') {
            return 'electron';
        }
        if ($brand == 'MC') {
            return 'maestro';
        }
        return null;
    }
}